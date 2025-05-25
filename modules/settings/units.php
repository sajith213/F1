<?php
/**
 * Unit Management
 * 
 * This page allows administrators to manage measurement units
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
        $unit_type = $_POST['unit_type'];
        $is_base_unit = isset($_POST['is_base_unit']) ? 1 : 0;
        $base_unit_id = (!$is_base_unit && !empty($_POST['base_unit_id'])) ? $_POST['base_unit_id'] : null;
        $conversion_factor = (!$is_base_unit && !empty($_POST['conversion_factor'])) ? $_POST['conversion_factor'] : null;
        $status = $_POST['status'];
        
        // Validate data
        if (empty($unit_name) || empty($unit_symbol) || empty($unit_type)) {
            $error_message = "Please fill all required fields";
        } else {
            try {
                if (isset($_POST['add_unit'])) {
                    // Add new unit
                    $stmt = $conn->prepare("
                        INSERT INTO units (
                            unit_name, unit_symbol, unit_type, is_base_unit, 
                            base_unit_id, conversion_factor, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "sssidss",
                        $unit_name, $unit_symbol, $unit_type, $is_base_unit, 
                        $base_unit_id, $conversion_factor, $status
                    );
                    $stmt->execute();
                    $success_message = "Unit added successfully";
                } else {
                    // Update existing unit
                    $stmt = $conn->prepare("
                        UPDATE units SET
                            unit_name = ?, unit_symbol = ?, unit_type = ?, 
                            is_base_unit = ?, base_unit_id = ?, conversion_factor = ?, 
                            status = ?
                        WHERE unit_id = ?
                    ");
                    $stmt->bind_param(
                        "sssidssi",
                        $unit_name, $unit_symbol, $unit_type, $is_base_unit, 
                        $base_unit_id, $conversion_factor, $status, $unit_id
                    );
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
                WHERE base_unit_id = ? OR purchase_unit_id = ? OR sale_unit_id = ?
            ");
            $checkStmt->bind_param("iii", $unit_id, $unit_id, $unit_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($row['product_count'] > 0) {
                $error_message = "Cannot delete this unit because it is being used by products";
            } else {
                // Check if unit is a base unit for other units
                $checkStmt = $conn->prepare("
                    SELECT COUNT(*) as unit_count
                    FROM units
                    WHERE base_unit_id = ?
                ");
                $checkStmt->bind_param("i", $unit_id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $row = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($row['unit_count'] > 0) {
                    $error_message = "Cannot delete this unit because it is a base unit for other units";
                } else {
                    // Delete the unit
                    $stmt = $conn->prepare("DELETE FROM units WHERE unit_id = ?");
                    $stmt->bind_param("i", $unit_id);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Unit deleted successfully";
                }
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get all units
$units = [];
$stmt = $conn->prepare("
    SELECT u.*, b.unit_name as base_unit_name, b.unit_symbol as base_unit_symbol
    FROM units u
    LEFT JOIN units b ON u.base_unit_id = b.unit_id
    ORDER BY u.unit_type, u.unit_name
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();

// Group units by type
$unitsByType = [];
foreach ($units as $unit) {
    $type = $unit['unit_type'];
    if (!isset($unitsByType[$type])) {
        $unitsByType[$type] = [];
    }
    $unitsByType[$type][] = $unit;
}

// Get base units for select dropdowns
$baseUnits = array_filter($units, function($unit) {
    return $unit['is_base_unit'] == 1;
});
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
        
        <!-- Units table with tabs for unit types -->
        <div class="p-6">
            <!-- Unit type tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-6" id="unit-tabs">
                    <a href="#all" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600" data-tab="all">
                        All Units
                    </a>
                    <?php foreach (array_keys($unitsByType) as $type): ?>
                    <a href="#<?= $type ?>" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="<?= $type ?>">
                        <?= ucfirst($type) ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            
            <!-- Units table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Unit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversion</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="units-table-all">
                        <?php if (empty($units)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-sm text-center text-gray-500">No units found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($units as $unit): ?>
                            <tr class="unit-row" data-type="<?= htmlspecialchars($unit['unit_type']) ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= htmlspecialchars($unit['unit_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium">
                                    <?= htmlspecialchars($unit['unit_symbol']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= ucfirst(htmlspecialchars($unit['unit_type'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($unit['is_base_unit']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Base Unit
                                        </span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($unit['base_unit_name'] ?? 'None') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!$unit['is_base_unit'] && $unit['conversion_factor']): ?>
                                        1 <?= htmlspecialchars($unit['unit_symbol']) ?> = 
                                        <?= htmlspecialchars($unit['conversion_factor']) ?> 
                                        <?= htmlspecialchars($unit['base_unit_symbol'] ?? '') ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
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
                </div>
                
                <div>
                    <label for="unit_symbol" class="block text-sm font-medium text-gray-700">Unit Symbol <span class="text-red-500">*</span></label>
                    <input type="text" id="unit_symbol" name="unit_symbol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    <p class="text-xs text-gray-500 mt-1">Short symbol like 'kg', 'L', etc.</p>
                </div>
                
                <div>
                    <label for="unit_type" class="block text-sm font-medium text-gray-700">Unit Type <span class="text-red-500">*</span></label>
                    <select id="unit_type" name="unit_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                        <option value="volume">Volume</option>
                        <option value="weight">Weight</option>
                        <option value="length">Length</option>
                        <option value="quantity">Quantity</option>
                        <option value="time">Time</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <div class="flex items-center">
                        <input type="checkbox" id="is_base_unit" name="is_base_unit" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="is_base_unit" class="ml-2 block text-sm text-gray-700">This is a base unit</label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Base units are used for conversions</p>
                </div>
                
                <div id="conversion-section">
                    <div>
                        <label for="base_unit_id" class="block text-sm font-medium text-gray-700">Base Unit</label>
                        <select id="base_unit_id" name="base_unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Select Base Unit</option>
                            <?php foreach ($baseUnits as $baseUnit): ?>
                            <option value="<?= $baseUnit['unit_id'] ?>" data-type="<?= $baseUnit['unit_type'] ?>" data-symbol="<?= htmlspecialchars($baseUnit['unit_symbol']) ?>">
                                <?= htmlspecialchars($baseUnit['unit_name']) ?> (<?= htmlspecialchars($baseUnit['unit_symbol']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mt-4">
                        <label for="conversion_factor" class="block text-sm font-medium text-gray-700">Conversion Factor</label>
                        <div class="mt-1 flex items-center">
                            <span class="mr-2">1</span>
                            <span id="unit_symbol_display" class="mr-2">[Unit]</span>
                            <span class="mr-2">=</span>
                            <input type="number" id="conversion_factor" name="conversion_factor" step="0.000001" min="0.000001" class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <span id="base_unit_symbol_display" class="ml-2">[Base Unit]</span>
                        </div>
                    </div>
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

<!-- JavaScript for modal and tab functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabs = document.querySelectorAll('#unit-tabs a');
    const unitRows = document.querySelectorAll('.unit-row');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            tabs.forEach(t => {
                t.classList.remove('border-blue-500', 'text-blue-600');
                t.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            });
            
            // Add active class to clicked tab
            this.classList.add('border-blue-500', 'text-blue-600');
            this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            
            // Show/hide units based on selected tab
            const tabType = this.getAttribute('data-tab');
            
            unitRows.forEach(row => {
                if (tabType === 'all' || row.getAttribute('data-type') === tabType) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        });
    });
    
    // Unit Modal Functionality
    const unitModal = document.getElementById('unit-modal');
    const unitForm = document.getElementById('unit-form');
    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const addUnitBtn = document.getElementById('add-unit-btn');
    
    // Is Base Unit Checkbox
    const isBaseUnitCheckbox = document.getElementById('is_base_unit');
    const conversionSection = document.getElementById('conversion-section');
    const baseUnitSelect = document.getElementById('base_unit_id');
    const unitTypeSelect = document.getElementById('unit_type');
    const unitSymbolInput = document.getElementById('unit_symbol');
    const unitSymbolDisplay = document.getElementById('unit_symbol_display');
    const baseUnitSymbolDisplay = document.getElementById('base_unit_symbol_display');
    
    // Show/hide conversion based on is_base_unit
    isBaseUnitCheckbox.addEventListener('change', function() {
        if (this.checked) {
            conversionSection.classList.add('hidden');
        } else {
            conversionSection.classList.remove('hidden');
            filterBaseUnits();
        }
    });
    
    // Filter base units based on unit type
    unitTypeSelect.addEventListener('change', filterBaseUnits);
    
    function filterBaseUnits() {
        const selectedType = unitTypeSelect.value;
        
        // Reset the select
        baseUnitSelect.selectedIndex = 0;
        
        // Show/hide options based on type
        Array.from(baseUnitSelect.options).forEach(option => {
            if (option.value === '') return; // Skip the placeholder option
            
            const optionType = option.getAttribute('data-type');
            if (optionType === selectedType) {
                option.hidden = false;
            } else {
                option.hidden = true;
            }
        });
    }
    
    // Update unit symbol display
    unitSymbolInput.addEventListener('input', function() {
        unitSymbolDisplay.textContent = this.value || '[Unit]';
    });
    
    // Update base unit symbol display
    baseUnitSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        baseUnitSymbolDisplay.textContent = selectedOption.value 
            ? selectedOption.getAttribute('data-symbol') 
            : '[Base Unit]';
    });
    
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
            document.getElementById('unit_type').value = unitData.unit_type;
            document.getElementById('is_base_unit').checked = unitData.is_base_unit == 1;
            document.getElementById('status').value = unitData.status;
            
            if (unitData.is_base_unit == 1) {
                conversionSection.classList.add('hidden');
            } else {
                conversionSection.classList.remove('hidden');
                document.getElementById('base_unit_id').value = unitData.base_unit_id || '';
                document.getElementById('conversion_factor').value = unitData.conversion_factor || '';
            }
            
            // Update displays
            unitSymbolDisplay.textContent = unitData.unit_symbol || '[Unit]';
            baseUnitSymbolDisplay.textContent = unitData.base_unit_symbol || '[Base Unit]';
            
            // Filter base units
            filterBaseUnits();
            
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
        
        // Show conversion section by default
        isBaseUnitCheckbox.checked = false;
        conversionSection.classList.remove('hidden');
        
        // Reset displays
        unitSymbolDisplay.textContent = '[Unit]';
        baseUnitSymbolDisplay.textContent = '[Base Unit]';
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