<?php
ob_start();
/**
 * Fuel Ordering Module - Update Order
 * 
 * This page allows users to update an existing fuel purchase order
 * including order details and items.
 */

// Set page title
$page_title = "Update Fuel Order";

// Set breadcrumbs (will be updated with order number once loaded)
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               <a href="view_orders.php" class="text-blue-600 hover:text-blue-800">View Orders</a> / 
               Update Order';

// Include header
include_once '../../includes/header.php';

// Include module functions
require_once 'functions.php';

// Check for permissions
if (!in_array($user_data['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Database connection
require_once '../../includes/db.php';

// Get currency symbol
$currency_symbol = get_currency_symbol();

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Invalid order ID. Please <a href="view_orders.php" class="underline">return to the orders list</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get order details
$order_query = "
    SELECT po.po_id, po.po_number, po.supplier_id, po.order_date, po.expected_delivery_date, 
           po.status, po.total_amount, po.payment_status, po.notes,
           s.supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE po.po_id = ?
";

$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Order not found. Please <a href="view_orders.php" class="underline">return to the orders list</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

$order = $result->fetch_assoc();

// Check if order is in a state that can be updated
if (!in_array($order['status'], ['draft', 'submitted'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>This order cannot be updated because it is in the "' . ucfirst($order['status']) . '" state.</p>
            <p>Only orders in "Draft" or "Submitted" state can be updated.</p>
            <p><a href="order_details.php?id=' . $order_id . '" class="underline">Return to order details</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Update breadcrumbs with order number
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               <a href="view_orders.php" class="text-blue-600 hover:text-blue-800">View Orders</a> / 
               <a href="order_details.php?id=' . $order_id . '" class="text-blue-600 hover:text-blue-800">' . htmlspecialchars($order['po_number']) . '</a> / 
               Update';


// Get order items
$items_query = "
    SELECT poi.po_item_id, poi.fuel_type_id, poi.quantity, poi.unit_price,
           poi.invoice_value, -- <-- Fetching the stored invoice value
           ft.fuel_name
    FROM po_items poi
    JOIN fuel_types ft ON poi.fuel_type_id = ft.fuel_type_id
    WHERE poi.po_id = ?
    ORDER BY poi.po_item_id
";

$stmt = $conn->prepare($items_query); // Line 106 // <-- Modified SQL

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];

if ($items_result) {
    while ($item = $items_result->fetch_assoc()) {
        // NO LONGER NEEDED: // $item['invoice_value'] = $item['quantity'] * $item['unit_price']; // <-- REMOVED
        $order_items[] = $item;
    }
}
// Process form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $order_date = isset($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
    $expected_delivery_date = isset($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : $order['status'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Validate form data
    if ($supplier_id <= 0) {
        $errors[] = "Please select a valid supplier.";
    }
    
    if (empty($order_date)) {
        $errors[] = "Order date is required.";
    }
    
    // Make sure at least one fuel item is selected
    $item_ids = isset($_POST['item_id']) ? $_POST['item_id'] : [];
    $fuel_types = isset($_POST['fuel_type']) ? $_POST['fuel_type'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $invoice_values = isset($_POST['invoice_value']) ? $_POST['invoice_value'] : [];
    
    if (empty($fuel_types) && empty($item_ids)) {
        $errors[] = "Please add at least one fuel item to the order.";
    }
    
    // Calculate total amount
    $total_amount = 0;
    
    // Existing items
    foreach ($item_ids as $key => $item_id) {
        if (isset($invoice_values[$key])) {
            $total_amount += floatval($invoice_values[$key]);
        }
    }
    
    // New items
    foreach ($fuel_types as $key => $fuel_type_id) {
        if (isset($invoice_values["new_$key"])) {
            $total_amount += floatval($invoice_values["new_$key"]);
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update purchase order header
            $sql = "UPDATE purchase_orders 
                    SET supplier_id = ?, order_date = ?, expected_delivery_date = ?, 
                        status = ?, total_amount = ?, notes = ?, updated_at = NOW()
                    WHERE po_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssdsi", $supplier_id, $order_date, $expected_delivery_date, 
                             $status, $total_amount, $notes, $order_id);
            $stmt->execute();
            
            // Update existing items
            // Update existing items
            if (!empty($item_ids)) {
                // Add invoice_value to the UPDATE statement
                $update_item_sql = "UPDATE po_items SET fuel_type_id = ?, quantity = ?, unit_price = ?, invoice_value = ? WHERE po_item_id = ?"; // <-- Changed SQL
                $stmt = $conn->prepare($update_item_sql);

                foreach ($item_ids as $key => $item_id) {
                    // Check if this item is marked for deletion
                    if (isset($_POST['delete_item'][$item_id]) && $_POST['delete_item'][$item_id] === '1') {
                        continue; // Skip update if marked for deletion
                    }

                    if (!empty($quantities[$key]) && !empty($invoice_values[$key])) {
                        $fuel_type_id = intval($_POST['existing_fuel_type'][$key]);
                        $qty = floatval($quantities[$key]);
                        $invoice_value = floatval($invoice_values[$key]); // Keep this

                        // Calculate unit price (still useful)
                        $unit_price = $qty > 0 ? ($invoice_value / $qty) : 0; // Keep this

                        if ($qty > 0 && $invoice_value > 0) {
                            // Update bind_param to include invoice_value (add 'd')
                            $stmt->bind_param("idddi", $fuel_type_id, $qty, $unit_price, $invoice_value, $item_id); // <-- Changed binding
                            $stmt->execute();
                        }
                    }
                }
            }
            
            // Delete items if requested
            if (isset($_POST['delete_item']) && is_array($_POST['delete_item'])) {
                $delete_item_sql = "DELETE FROM po_items WHERE po_item_id = ?";
                $stmt = $conn->prepare($delete_item_sql);
                
                foreach ($_POST['delete_item'] as $item_id => $value) {
                    if ($value === '1') {
                        $stmt->bind_param("i", $item_id);
                        $stmt->execute();
                    }
                }
            }
            
           // Add new items
        if (!empty($fuel_types)) {
            // Add invoice_value to the INSERT statement
           $insert_item_sql = "INSERT INTO po_items (po_id, fuel_type_id, quantity, unit_price, invoice_value) VALUES (?, ?, ?, ?, ?)"; // <-- Changed SQL
           $stmt = $conn->prepare($insert_item_sql);

           foreach ($fuel_types as $key => $fuel_type_id) {
                // Make sure the keys exist before trying to access them
               $qty_key = "new_$key";
               $inv_key = "new_$key";
               if (isset($quantities[$qty_key]) && isset($invoice_values[$inv_key]) && !empty($quantities[$qty_key]) && !empty($invoice_values[$inv_key])) {
                   $qty = floatval($quantities[$qty_key]);
                   $invoice_value = floatval($invoice_values[$inv_key]);

                   // Calculate unit price
                   $unit_price = $qty > 0 ? ($invoice_value / $qty) : 0;

                   if ($qty > 0 && $invoice_value > 0) {
                       // Update bind_param to include invoice_value (add 'd')
                       $stmt->bind_param("idddd", $order_id, $fuel_type_id, $qty, $unit_price, $invoice_value); // <-- Changed binding
                       $stmt->execute();
                   }
               }
           }
       }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Purchase order has been updated successfully.";
            
            // Redirect on success
            header("Location: order_details.php?id=$order_id&success=updated");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Error updating purchase order: " . $e->getMessage();
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
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form id="update-order-form" method="POST" action="">
            <!-- Order Header Information -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Order Number (read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                        <input type="text" value="<?= htmlspecialchars($order['po_number']) ?>" class="w-full rounded-md border-gray-300 bg-gray-100 shadow-sm" readonly>
                    </div>
                    
                    <!-- Supplier -->
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                        <select id="supplier_id" name="supplier_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                            <option value="">Select Supplier</option>
                            <?php if ($suppliers_result): ?>
                                <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                <option value="<?= $supplier['supplier_id'] ?>" <?= $supplier['supplier_id'] == $order['supplier_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <!-- Order Date -->
                    <div>
                        <label for="order_date" class="block text-sm font-medium text-gray-700 mb-1">Order Date <span class="text-red-500">*</span></label>
                        <input type="date" id="order_date" name="order_date" value="<?= htmlspecialchars($order['order_date']) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                    
                    <!-- Expected Delivery Date -->
                    <div>
                        <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery Date</label>
                        <input type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?= htmlspecialchars($order['expected_delivery_date'] ?? '') ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="draft" <?= $order['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="submitted" <?= $order['status'] === 'submitted' ? 'selected' : '' ?>>Submit for Approval</option>
                            <?php if ($user_data['role'] === 'admin'): ?>
                            <option value="approved" <?= $order['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Existing Order Items -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Order Items</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (Liters)</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Value</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price (Calculated)</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="existing-items" class="divide-y divide-gray-200">
                            <?php if (!empty($order_items)): ?>
                                <?php foreach ($order_items as $index => $item): ?>
                                <tr class="item-row">
                                    <td class="px-4 py-3">
                                        <input type="hidden" name="item_id[]" value="<?= $item['po_item_id'] ?>">
                                        <select name="existing_fuel_type[]" class="fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            <?php foreach ($fuel_types_data as $fuel_type): ?>
                                                <option value="<?= $fuel_type['fuel_type_id'] ?>" <?= $fuel_type['fuel_type_id'] == $item['fuel_type_id'] ? 'selected' : '' ?> data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                                                    <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="quantity[]" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01" value="<?= number_format($item['quantity'], 2, '.', '') ?>" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="invoice_value[]" class="invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01" value="<?= number_format($item['invoice_value'], 2, '.', '') ?>" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="unit-price"><?= $currency_symbol ?><?= number_format($item['unit_price'], 2) ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="delete_item[<?= $item['po_item_id'] ?>]" value="1" class="delete-checkbox rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-red-600">Delete</span>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add New Items Section -->
                <div class="mt-4">
                    <h3 class="text-md font-medium text-gray-800 mb-2">Add New Items</h3>
                    
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
                            <tbody id="new-items" class="divide-y divide-gray-200">
                                <tr class="new-item-row">
                                    <td class="px-4 py-3">
                                        <select name="fuel_type[]" class="new-fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="">Select Fuel Type</option>
                                            <?php foreach ($fuel_types_data as $fuel_type): ?>
                                                <option value="<?= $fuel_type['fuel_type_id'] ?>" data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                                                    <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="quantity[new_0]" class="new-quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="invoice_value[new_0]" class="new-invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01">
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="new-unit-price"><?= $currency_symbol ?>0.00</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" id="add-new-item-btn" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-plus-circle"></i> Add More
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-right font-semibold">Total Amount:</td>
                                    <td class="px-4 py-3 font-bold">
                                        <span id="order-total"><?= $currency_symbol ?><?= number_format($order['total_amount'] ?? 0, 2) ?></span>
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
            </div>
            
            <!-- Form Buttons -->
            <div class="flex justify-end space-x-3">
                <a href="order_details.php?id=<?= $order_id ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    Update Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- New item template for JavaScript -->
<template id="new-item-template">
    <tr class="new-item-row">
        <td class="px-4 py-3">
            <select name="fuel_type[]" class="new-fuel-type-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                <option value="">Select Fuel Type</option>
                <?php foreach ($fuel_types_data as $fuel_type): ?>
                    <option value="<?= $fuel_type['fuel_type_id'] ?>" data-price="<?= isset($fuel_prices[$fuel_type['fuel_type_id']]) ? number_format($fuel_prices[$fuel_type['fuel_type_id']], 2) : '0.00' ?>">
                        <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-4 py-3">
            <input type="number" name="quantity[new_INDEX]" class="new-quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="1" step="0.01">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="invoice_value[new_INDEX]" class="new-invoice-value-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" min="0.01" step="0.01">
        </td>
        <td class="px-4 py-3">
            <span class="new-unit-price"><?= $currency_symbol ?>0.00</span>
        </td>
        <td class="px-4 py-3">
            <button type="button" class="remove-new-item-btn text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i> Remove
            </button>
        </td>
    </tr>
</template>

<?php
// Add JavaScript for dynamic form
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Add new item button functionality
    const addNewItemBtn = document.getElementById("add-new-item-btn");
    const newItems = document.getElementById("new-items");
    const newItemTemplate = document.getElementById("new-item-template");
    let newItemCounter = 1; // Starting from 1 because we already have item 0
    
    if (addNewItemBtn) {
        addNewItemBtn.addEventListener("click", function() {
            const content = newItemTemplate.innerHTML.replace(/new_INDEX/g, "new_" + newItemCounter);
            const tr = document.createElement("tr");
            tr.className = "new-item-row";
            tr.innerHTML = content;
            newItems.appendChild(tr);
            newItemCounter++;
            updateEventListeners();
            calculateTotals();
        });
    }
    
    // Initialize event listeners
    updateEventListeners();
    calculateTotals();
    
    // Function to update event listeners for dynamic elements
    function updateEventListeners() {
        // Remove new item button functionality
        const removeNewButtons = document.querySelectorAll(".remove-new-item-btn");
        removeNewButtons.forEach(button => {
            button.addEventListener("click", function() {
                this.closest("tr").remove();
                calculateTotals();
            });
        });
        
        // Calculate unit prices and totals when quantities or invoice values change
        const quantityInputs = document.querySelectorAll(".quantity-input, .new-quantity-input");
        const invoiceValueInputs = document.querySelectorAll(".invoice-value-input, .new-invoice-value-input");
        
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
        const fuelTypeSelects = document.querySelectorAll(".fuel-type-select, .new-fuel-type-select");
        fuelTypeSelects.forEach(select => {
            select.addEventListener("change", function() {
                // Instead of calculating invoice value automatically, just ensure unit price is updated
                const row = this.closest("tr");
                const quantityInput = row.querySelector(".quantity-input") || row.querySelector(".new-quantity-input");
                const invoiceValueInput = row.querySelector(".invoice-value-input") || row.querySelector(".new-invoice-value-input");
                
                // Only calculate unit price if both values are already entered
                if (quantityInput.value && invoiceValueInput.value) {
                    calculateUnitPrice(invoiceValueInput);
                    calculateTotals();
                }
            });
        });
        
        // Handle delete checkboxes to visually indicate deleted items
        const deleteCheckboxes = document.querySelectorAll(".delete-checkbox");
        deleteCheckboxes.forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                const row = this.closest("tr");
                if (this.checked) {
                    row.classList.add("bg-red-50");
                } else {
                    row.classList.remove("bg-red-50");
                }
                calculateTotals();
            });
        });
    }
    
    // Function to calculate unit price from quantity and invoice value
    function calculateUnitPrice(input) {
        const row = input.closest("tr");
        const quantityInput = row.querySelector(".quantity-input") || row.querySelector(".new-quantity-input");
        const invoiceValueInput = row.querySelector(".invoice-value-input") || row.querySelector(".new-invoice-value-input");
        const unitPriceSpan = row.querySelector(".unit-price") || row.querySelector(".new-unit-price");
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const invoiceValue = parseFloat(invoiceValueInput.value) || 0;
        
        if (quantity > 0 && invoiceValue > 0) {
            const unitPrice = invoiceValue / quantity;
            unitPriceSpan.textContent = "' . $currency_symbol . '" + unitPrice.toFixed(2);
        } else {
            unitPriceSpan.textContent = "' . $currency_symbol . '0.00";
        }
    }
    
    // Function to calculate totals
    function calculateTotals() {
        let orderTotal = 0;
        
        // Calculate for existing items
        document.querySelectorAll(".item-row").forEach(row => {
            const deleteCheckbox = row.querySelector(".delete-checkbox");
            if (deleteCheckbox && deleteCheckbox.checked) {
                // Skip deleted items
                return;
            }
            
            const invoiceValueInput = row.querySelector(".invoice-value-input");
            const invoiceValue = parseFloat(invoiceValueInput.value) || 0;
            orderTotal += invoiceValue;
        });
        
        // Calculate for new items
        document.querySelectorAll(".new-item-row").forEach(row => {
            const invoiceValueInput = row.querySelector(".new-invoice-value-input");
            
            if (invoiceValueInput) {
                const invoiceValue = parseFloat(invoiceValueInput.value) || 0;
                orderTotal += invoiceValue;
            }
        });
        
        // Use toFixed(2) to ensure we always show exactly 2 decimal places
        document.getElementById("order-total").textContent = "' . $currency_symbol . '" + orderTotal.toFixed(2);
    }
});
</script>
';

// Include footer
include_once '../../includes/footer.php';

ob_end_flush();
?>