<?php
ob_start();
/**
 * Fuel Ordering Module - Create Order
 * 
 * This page allows users to create a new fuel purchase order
 * with multiple fuel types and quantities.
 */

// Set page title
$page_title = "Create Fuel Order";

// Set breadcrumbs
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               Create Order';

// Include header
include_once '../../includes/header.php';

// Include module functions
require_once 'functions.php';

// Check for permissions
if (!in_array($user_data['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Database connection
require_once '../../includes/db.php';

// Get currency symbol
$currency_symbol = get_currency_symbol();

// Process form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $order_date = isset($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
    $expected_delivery_date = isset($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : 'draft';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate form data
    if ($supplier_id <= 0) {
        $errors[] = "Please select a valid supplier.";
    }
    
    if (empty($order_date)) {
        $errors[] = "Order date is required.";
    }
    
    // Make sure at least one fuel item is selected
    $fuel_types = isset($_POST['fuel_type']) ? $_POST['fuel_type'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $invoice_values = isset($_POST['invoice_value']) ? $_POST['invoice_value'] : [];
    
    if (empty($fuel_types)) {
        $errors[] = "Please add at least one fuel item to the order.";
    }
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($invoice_values as $key => $invoice_value) {
        if (is_numeric($invoice_value)) {
            $total_amount += floatval($invoice_value);
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Generate PO number (format: PO-YYYYMMDD-XXX)
            $po_prefix = "PO-" . date('Ymd') . "-";
            $sql = "SELECT MAX(po_number) as max_po FROM purchase_orders WHERE po_number LIKE ?";
            $stmt = $conn->prepare($sql);
            $like_param = $po_prefix . '%';
            $stmt->bind_param("s", $like_param);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $next_number = 1;
            if ($row && $row['max_po']) {
                $last_number = substr($row['max_po'], -3);
                $next_number = intval($last_number) + 1;
            }
            
            $po_number = $po_prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
            
            // Insert purchase order header
            $sql = "INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, 
                    status, total_amount, payment_status, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisssdss", $po_number, $supplier_id, $order_date, $expected_delivery_date, 
                              $status, $total_amount, $notes, $_SESSION['user_id']);
            $stmt->execute();
            
            $po_id = $conn->insert_id;
            
           // Insert order items
        // Add invoice_value to the INSERT statement
        $sql = "INSERT INTO po_items (po_id, fuel_type_id, quantity, unit_price, invoice_value) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        foreach ($fuel_types as $key => $fuel_type_id) {
            if (!empty($quantities[$key]) && !empty($invoice_values[$key])) {
                $qty = floatval($quantities[$key]);
                $invoice_value = floatval($invoice_values[$key]); // Keep this line

                // Calculate unit price (still useful for display/info)
                $unit_price = $qty > 0 ? ($invoice_value / $qty) : 0;

                if ($qty > 0 && $invoice_value > 0) {
                    // Update bind_param to include invoice_value (add 'd' for decimal/double)
                    $stmt->bind_param("idddd", $po_id, $fuel_type_id, $qty, $unit_price, $invoice_value); // <-- Changed binding
                    $stmt->execute();
                }
            }
        }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Purchase order <strong>$po_number</strong> has been created successfully.";
            
            // Redirect to view page on success
            if ($status !== 'draft') {
                header("Location: order_details.php?id=$po_id&success=created");
                exit;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error creating purchase order: " . $e->getMessage();
        }
    }
}

// Get all suppliers
$suppliers_query = "SELECT supplier_id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Get all fuel types
$fuel_types_query = "SELECT fuel_type_id, fuel_name FROM fuel_types ORDER BY fuel_name";
$fuel_types_result = $conn->query($fuel_types_query);
$fuel_types_data = [];
if ($fuel_types_result) {
    while ($row = $fuel_types_result->fetch_assoc()) {
        $fuel_types_data[] = $row;
    }
}

// Get latest fuel prices
$latest_prices_query = "SELECT fp.fuel_type_id, MAX(fp.price_id) as latest_price_id 
                        FROM fuel_prices fp 
                        WHERE fp.effective_date <= CURDATE() 
                        GROUP BY fp.fuel_type_id";
$latest_prices_result = $conn->query($latest_prices_query);

$fuel_prices = [];
if ($latest_prices_result) {
    $price_ids = [];
    while ($row = $latest_prices_result->fetch_assoc()) {
        $price_ids[] = $row['latest_price_id'];
    }
    
    if (!empty($price_ids)) {
        $price_ids_str = implode(',', $price_ids);
        $prices_query = "SELECT fp.fuel_type_id, fp.purchase_price 
                         FROM fuel_prices fp 
                         WHERE fp.price_id IN ($price_ids_str)";
        $prices_result = $conn->query($prices_query);
        
        if ($prices_result) {
            while ($row = $prices_result->fetch_assoc()) {
                $fuel_prices[$row['fuel_type_id']] = $row['purchase_price'];
            }
        }
    }
}

// Check for pre-selected fuel type (from low stock alert)
$preselected_fuel_type = isset($_GET['fuel_type']) ? intval($_GET['fuel_type']) : 0;
?>

<!-- Page content -->
<div class="container mx-auto px-4 py-4">
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Please fix the following errors:</p>
        <ul class="list-disc ml-5">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= $success_message ?></p>
        <p class="mt-2">
            <a href="order_details.php?id=<?= $po_id ?>" class="text-green-600 underline">View order details</a> or
            <a href="create_order.php" class="text-green-600 underline">create another order</a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form id="create-order-form" method="POST" action="">
            <!-- Order Header Information -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Supplier -->
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                        <select id="supplier_id" name="supplier_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                            <option value="">Select Supplier</option>
                            <?php if ($suppliers_result): ?>
                                <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                <option value="<?= $supplier['supplier_id'] ?>" <?= isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['supplier_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <!-- Order Date -->
                    <div>
                        <label for="order_date" class="block text-sm font-medium text-gray-700 mb-1">Order Date <span class="text-red-500">*</span></label>
                        <input type="date" id="order_date" name="order_date" value="<?= isset($_POST['order_date']) ? htmlspecialchars($_POST['order_date']) : date('Y-m-d') ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                    
                    <!-- Expected Delivery Date -->
                    <div>
                        <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery Date</label>
                        <input type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?= isset($_POST['expected_delivery_date']) ? htmlspecialchars($_POST['expected_delivery_date']) : date('Y-m-d', strtotime('+3 days')) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="draft" <?= isset($_POST['status']) && $_POST['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="submitted" <?= isset($_POST['status']) && $_POST['status'] === 'submitted' ? 'selected' : '' ?>>Submit for Approval</option>
                            <?php if ($user_data['role'] === 'admin'): ?>
                            <option value="approved" <?= isset($_POST['status']) && $_POST['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Order Items</h2>
                    <button type="button" id="add-item-btn" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                        <i class="fas fa-plus mr-1"></i> Add Item
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (Liters)</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Value</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price (Calculated)</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="order-items" class="divide-y divide-gray-200">
                            <!-- Order items will be added here -->
                            <?php if (empty($_POST) && $preselected_fuel_type > 0): ?>
                                <!-- Add a pre-selected item if coming from low stock alert -->
                                <tr class="item-row">
                                    <td class="px-4 py-3">
                                        <select name="fuel_type[]" class="fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            <option value="">Select Fuel Type</option>
                                            <?php foreach ($fuel_types_data as $fuel_type): ?>
                                                <option value="<?= $fuel_type['fuel_type_id'] ?>" <?= $fuel_type['fuel_type_id'] == $preselected_fuel_type ? 'selected' : '' ?> data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                                                    <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="quantity[]" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="invoice_value[]" class="invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="unit-price"><?= $currency_symbol ?>0.00</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" class="remove-item-btn text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php elseif (!empty($fuel_types) && is_array($fuel_types)): ?>
                                <!-- Repopulate submitted items on validation error -->
                                <?php foreach ($fuel_types as $key => $fuel_type_id): ?>
                                    <?php if (!empty($fuel_type_id)): ?>
                                    <tr class="item-row">
                                        <td class="px-4 py-3">
                                            <select name="fuel_type[]" class="fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                                <option value="">Select Fuel Type</option>
                                                <?php foreach ($fuel_types_data as $fuel_type): ?>
                                                    <option value="<?= $fuel_type['fuel_type_id'] ?>" <?= $fuel_type['fuel_type_id'] == $fuel_type_id ? 'selected' : '' ?> data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                                                        <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="quantity[]" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01" value="<?= isset($quantities[$key]) ? htmlspecialchars($quantities[$key]) : '' ?>" required>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="invoice_value[]" class="invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01" value="<?= isset($invoice_values[$key]) ? htmlspecialchars($invoice_values[$key]) : '' ?>" required>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="unit-price">
                                                <?php
                                                    $unit_price = 0;
                                                    if (isset($quantities[$key]) && isset($invoice_values[$key]) && $quantities[$key] > 0) {
                                                        $unit_price = $invoice_values[$key] / $quantities[$key];
                                                    }
                                                    echo $currency_symbol . number_format($unit_price, 2);
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <button type="button" class="remove-item-btn text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Default empty row -->
                                <tr class="item-row">
                                    <td class="px-4 py-3">
                                        <select name="fuel_type[]" class="fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            <option value="">Select Fuel Type</option>
                                            <?php foreach ($fuel_types_data as $fuel_type): ?>
                                                <option value="<?= $fuel_type['fuel_type_id'] ?>" data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                                                    <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="quantity[]" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="invoice_value[]" class="invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="unit-price"><?= $currency_symbol ?>0.00</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" class="remove-item-btn text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right font-semibold">Total Amount:</td>
                                <td class="px-4 py-3 font-bold">
                                    <span id="order-total"><?= $currency_symbol ?>0.00</span>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
            </div>
            
            <!-- Form Buttons -->
            <div class="flex justify-end space-x-3">
                <a href="index.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    Cancel
                </a>
                <button type="button" id="save-draft-btn" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    Save as Draft
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    Submit Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Item template for JavaScript -->
<template id="item-template">
    <tr class="item-row">
        <td class="px-4 py-3">
            <select name="fuel_type[]" class="fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                <option value="">Select Fuel Type</option>
                <?php foreach ($fuel_types_data as $fuel_type): ?>
                    <option value="<?= $fuel_type['fuel_type_id'] ?>" data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                        <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-4 py-3">
            <input type="number" name="quantity[]" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01" required>
        </td>
        <td class="px-4 py-3">
            <input type="number" name="invoice_value[]" class="invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01" required>
        </td>
        <td class="px-4 py-3">
            <span class="unit-price"><?= $currency_symbol ?>0.00</span>
        </td>
        <td class="px-4 py-3">
            <button type="button" class="remove-item-btn text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<?php
// Add JavaScript for dynamic form
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Add item button functionality
    const addItemBtn = document.getElementById("add-item-btn");
    const orderItems = document.getElementById("order-items");
    const itemTemplate = document.getElementById("item-template");
    
    addItemBtn.addEventListener("click", function() {
        const clone = document.importNode(itemTemplate.content, true);
        orderItems.appendChild(clone);
        updateEventListeners();
        calculateTotals();
    });
    
    // Save draft button functionality
    const saveDraftBtn = document.getElementById("save-draft-btn");
    const statusSelect = document.getElementById("status");
    
    saveDraftBtn.addEventListener("click", function() {
        statusSelect.value = "draft";
        document.getElementById("create-order-form").submit();
    });
    
    // Initialize event listeners
    updateEventListeners();
    calculateTotals();
    
    // Function to update event listeners for dynamic elements
    function updateEventListeners() {
        // Remove item button functionality
        const removeButtons = document.querySelectorAll(".remove-item-btn");
        removeButtons.forEach(button => {
            button.addEventListener("click", function() {
                if (document.querySelectorAll(".item-row").length > 1) {
                    // Only remove if there\'s more than one item
                    this.closest("tr").remove();
                    calculateTotals();
                } else {
                    alert("At least one item is required.");
                }
            });
        });
        
        // Calculate unit prices and totals when quantity or invoice value changes
        const quantityInputs = document.querySelectorAll(".quantity-input");
        const invoiceValueInputs = document.querySelectorAll(".invoice-value-input");
        
        quantityInputs.forEach(input => {
            input.addEventListener("input", function() {
                // Only update the unit price based on manually entered values
                calculateUnitPrice(this);
                calculateTotals();
            });
        });
        
        invoiceValueInputs.forEach(input => {
            input.addEventListener("input", function() {
                calculateUnitPrice(this);
                calculateTotals();
            });
        });
        
        // Modify the fuel type selection behavior
        const fuelTypeSelects = document.querySelectorAll(".fuel-type-select");
        fuelTypeSelects.forEach(select => {
            select.addEventListener("change", function() {
                // Instead of calculating invoice value automatically, just ensure unit price is updated
                const row = this.closest("tr");
                const quantityInput = row.querySelector(".quantity-input");
                const invoiceValueInput = row.querySelector(".invoice-value-input");
                
                // Only calculate unit price if both values are already entered
                if (quantityInput.value && invoiceValueInput.value) {
                    calculateUnitPrice(invoiceValueInput);
                    calculateTotals();
                }
            });
        });
    }
    
    // Function to calculate unit price from quantity and invoice value
    function calculateUnitPrice(input) {
        const row = input.closest("tr");
        const quantityInput = row.querySelector(".quantity-input");
        const invoiceValueInput = row.querySelector(".invoice-value-input");
        const unitPriceSpan = row.querySelector(".unit-price");
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const invoiceValue = parseFloat(invoiceValueInput.value) || 0;
        
        if (quantity > 0 && invoiceValue > 0) {
            const unitPrice = invoiceValue / quantity;
            unitPriceSpan.textContent = "' . $currency_symbol . '" + unitPrice.toFixed(2);
        } else {
            unitPriceSpan.textContent = "' . $currency_symbol . '0.00";
        }
    }
    
    // Function to calculate invoice totals
    function calculateTotals() {
        let orderTotal = 0;
        
        document.querySelectorAll(".item-row").forEach(row => {
            const invoiceValueInput = row.querySelector(".invoice-value-input");
            const invoiceValue = parseFloat(invoiceValueInput.value) || 0;
            orderTotal += invoiceValue;
        });
        
        // Use toFixed(2) to ensure we always show exactly 2 decimal places
        document.getElementById("order-total").textContent = "' . $currency_symbol . '" + orderTotal.toFixed(2);
    }
});
</script>
';

// Include footer
include_once '../../includes/footer.php';
?>