<?php

ob_start();
/**
 * Fuel Ordering Module - Order Details
 * * This page displays detailed information about a specific purchase order
 * including order items, delivery status, and payment information.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set page title
$page_title = "Purchase Order Details";

// Set breadcrumbs (will be updated with order number once loaded)
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               <a href="view_orders.php" class="text-blue-600 hover:text-blue-800">View Orders</a> / Details';

// Include header
include_once '../../includes/header.php';

// Include module functions
require_once 'functions.php';

// Check for permissions
if (!in_array($user_data['role'], ['admin', 'manager', 'cashier'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Database connection
require_once '../../includes/db.php';

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Invalid order ID. Please <a href="view_orders.php" class="underline">return to the orders list</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get currency symbol from settings
$currency_symbol = get_currency_symbol();

// Get order details
$order_query = "
    SELECT po.po_id, po.po_number, po.order_date, po.expected_delivery_date, 
           po.status, po.total_amount, po.payment_status, po.payment_date,
           po.payment_reference, po.notes, po.created_at, po.updated_at,
           s.supplier_id, s.supplier_name, s.contact_person, s.phone, s.email,
           u.full_name as created_by
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN users u ON po.created_by = u.user_id
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

// Update breadcrumbs with order number
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               <a href="view_orders.php" class="text-blue-600 hover:text-blue-800">View Orders</a> / 
               ' . htmlspecialchars($order['po_number']);

// Get order items (Fetch invoice_value directly)
$items_query = "
    SELECT poi.po_item_id, poi.fuel_type_id, poi.quantity, poi.unit_price, 
           poi.invoice_value,
           ft.fuel_name
    FROM po_items poi
    JOIN fuel_types ft ON poi.fuel_type_id = ft.fuel_type_id
    WHERE poi.po_id = ?
    ORDER BY poi.po_item_id
";

// Handle order cancellation
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && $user_data['role'] === 'admin') {
    $order_id = intval($_GET['id']);
    
    if ($order_id > 0) {
        // Check current order status
        $check_status_sql = "SELECT status FROM purchase_orders WHERE po_id = ?";
        $stmt = $conn->prepare($check_status_sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_status = $result->fetch_assoc()['status'];
        
        // Only allow cancellation if not already cancelled or delivered
        if ($current_status !== 'cancelled' && $current_status !== 'delivered') {
            // Update order status to cancelled
            $cancel_sql = "UPDATE purchase_orders SET status = 'cancelled' WHERE po_id = ?";
            $stmt = $conn->prepare($cancel_sql);
            $stmt->bind_param("i", $order_id);
            
            if ($stmt->execute()) {
                $delivery_message = "Order cancelled successfully.";
                
                // Refresh page to show updated status
                header("Location: order_details.php?id=$order_id&cancel_success=1");
                exit;
            } else {
                $delivery_error = "Error cancelling order: " . $stmt->error;
            }
        } else {
            $delivery_error = "Cannot cancel an order that is already cancelled or delivered.";
        }
    }
}

// Change order status from InProgress to Approved
if (isset($_GET['action']) && $_GET['action'] === 'change_status' && in_array($user_data['role'], ['admin', 'manager'])) {
    $new_status = isset($_GET['new_status']) ? $_GET['new_status'] : '';
    if ($new_status && in_array($new_status, ['approved', 'in_progress', 'submitted'])) {
        $update_sql = "UPDATE purchase_orders SET status = ? WHERE po_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $order_id);
        
        if ($stmt->execute()) {
            $delivery_message = "Order status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . " successfully.";
            
            // Refresh page to show updated status
            header("Location: order_details.php?id=$order_id&status_change_success=1");
            exit;
        } else {
            $delivery_error = "Error updating order status: " . $stmt->error;
        }
    } else {
        $delivery_error = "Invalid status selected.";
    }
}

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Get delivery information
$delivery_query = "
    SELECT fd.delivery_id, fd.delivery_date, fd.delivery_reference, fd.status as delivery_status,
           fd.notes as delivery_notes, u.full_name as received_by
    FROM fuel_deliveries fd
    JOIN users u ON fd.received_by = u.user_id
    WHERE fd.po_id = ?
    ORDER BY fd.delivery_date DESC
";

$stmt = $conn->prepare($delivery_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$delivery_result = $stmt->get_result();

// Process delivery action if requested
$delivery_message = '';
$delivery_error = '';
$edit_delivery_id = 0;
$delivery_data = null;

if (isset($_GET['action']) && $_GET['action'] === 'delivery' && $order['status'] === 'approved') {
    // Show delivery form
    $show_delivery_form = true;
}

// Handle editing existing delivery
if (isset($_GET['action']) && $_GET['action'] === 'edit_delivery' && in_array($user_data['role'], ['admin', 'manager'])) {
    $edit_delivery_id = isset($_GET['delivery_id']) ? intval($_GET['delivery_id']) : 0;
    
    if ($edit_delivery_id > 0) {
        // Get delivery details
        $edit_query = "
            SELECT fd.*, u.full_name as received_by_name
            FROM fuel_deliveries fd
            JOIN users u ON fd.received_by = u.user_id
            WHERE fd.delivery_id = ? AND fd.po_id = ?
        ";
        $stmt = $conn->prepare($edit_query);
        $stmt->bind_param("ii", $edit_delivery_id, $order_id);
        $stmt->execute();
        $edit_result = $stmt->get_result();
        
        if ($edit_result->num_rows === 1) {
            $delivery_data = $edit_result->fetch_assoc();
            $show_delivery_form = true;
            
            // Get items for this delivery
            $delivery_items_query = "
                SELECT di.*, ft.fuel_name, t.tank_name
                FROM delivery_items di
                JOIN fuel_types ft ON di.fuel_type_id = ft.fuel_type_id
                JOIN tanks t ON di.tank_id = t.tank_id
                WHERE di.delivery_id = ?
            ";
            $stmt = $conn->prepare($delivery_items_query);
            $stmt->bind_param("i", $edit_delivery_id);
            $stmt->execute();
            $delivery_items_result = $stmt->get_result();
            $delivery_items = [];
            
            while ($item = $delivery_items_result->fetch_assoc()) {
                $delivery_items[] = $item;
            }
        } else {
            $delivery_error = "Delivery record not found or does not belong to this order.";
        }
    } else {
        $delivery_error = "Invalid delivery ID.";
    }
}

// Process delivery form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record_delivery' || $_POST['action'] === 'update_delivery') {
        // Validate form data
        $delivery_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : date('Y-m-d');
        $delivery_reference = isset($_POST['delivery_reference']) ? trim($_POST['delivery_reference']) : '';
        $delivery_notes = isset($_POST['delivery_notes']) ? trim($_POST['delivery_notes']) : '';
        $is_edit = ($_POST['action'] === 'update_delivery');
        $edit_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
        
        // Process fuel item deliveries
        $item_entries = isset($_POST['item_entries']) ? $_POST['item_entries'] : [];
        $errors = [];
        
        if (empty($delivery_date)) {
            $errors[] = "Delivery date is required.";
        }

        if (empty($item_entries)) {
            $errors[] = "No fuel items received. Please specify at least one delivery.";
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Validate and prepare the delivery data
            // Group entries by po_item_id to validate total quantities
            $items_data = [];
            $ordered_qty = [];
            
            // Get ordered quantities for each item
            $items_result->data_seek(0); // Reset result pointer
            while ($item = $items_result->fetch_assoc()) {
                $ordered_qty[$item['po_item_id']] = floatval($item['quantity']);
                $items_data[$item['po_item_id']] = [
                    'fuel_type_id' => $item['fuel_type_id'],
                    'ordered_qty' => floatval($item['quantity']),
                    'received_qty' => 0,
                    'tanks' => []
                ];
            }
            
            // If editing, get previous quantities for adjustment
            $previous_tank_quantities = [];
            if ($is_edit && $edit_id > 0) {
                $prev_qty_query = "
                    SELECT tank_id, quantity_received
                    FROM delivery_items
                    WHERE delivery_id = ?
                ";
                $stmt = $conn->prepare($prev_qty_query);
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
                $prev_result = $stmt->get_result();
                
                while ($prev = $prev_result->fetch_assoc()) {
                    $previous_tank_quantities[$prev['tank_id']] = floatval($prev['quantity_received']);
                }
                
                // First, delete existing delivery items to be replaced
                $delete_items_sql = "DELETE FROM delivery_items WHERE delivery_id = ?";
                $stmt = $conn->prepare($delete_items_sql);
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
            }
            
            // Process entries and validate
            foreach ($item_entries as $entry) {
                $po_item_id = intval($entry['po_item_id']);
                $tank_id = intval($entry['tank_id']);
                $quantity = floatval($entry['quantity']);
                $notes = isset($entry['notes']) ? trim($entry['notes']) : '';
                
                // Validate entry
                if (!isset($items_data[$po_item_id])) {
                    throw new Exception("Invalid item ID: $po_item_id");
                }
                
                if ($tank_id <= 0) {
                    throw new Exception("Please select a valid tank for each entry.");
                }
                
                if ($quantity <= 0) {
                    throw new Exception("Quantity must be greater than zero for each entry.");
                }
                
                // Add to total received and tank entries
                $items_data[$po_item_id]['received_qty'] += $quantity;
                $items_data[$po_item_id]['tanks'][] = [
                    'tank_id' => $tank_id,
                    'quantity' => $quantity,
                    'notes' => $notes
                ];
            }
            
            // Validate totals against ordered quantities
            $complete_delivery = true;
            foreach ($items_data as $po_item_id => $item) {
                $received_qty = $item['received_qty'];
                $ordered_qty = $item['ordered_qty'];
                
                if ($received_qty > $ordered_qty) {
                     throw new Exception("Total received quantity for item #$po_item_id (" . number_format($received_qty, 2) . "L) exceeds ordered quantity (" . number_format($ordered_qty, 2) . "L).");
                }
                
                // Check if any item received less than ordered
                if ($received_qty < $ordered_qty) {
                    $complete_delivery = false;
                }
            }
            
             // Check if at least one item was received
            $total_quantity_received_overall = 0;
            foreach ($items_data as $item) {
                $total_quantity_received_overall += $item['received_qty'];
            }
            if ($total_quantity_received_overall <= 0) {
                throw new Exception("You must record receiving a quantity greater than zero for at least one item.");
            }

            // Set delivery status based on completeness
            $delivery_status = $complete_delivery ? 'complete' : 'partial';
            
            if ($is_edit && $edit_id > 0) {
                // Update existing delivery record
                $update_sql = "
                    UPDATE fuel_deliveries 
                    SET delivery_date = ?, delivery_reference = ?, status = ?, notes = ?
                    WHERE delivery_id = ? AND po_id = ?
                ";
                
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ssssis", $delivery_date, $delivery_reference, $delivery_status, $delivery_notes, $edit_id, $order_id);
                $stmt->execute();
                
                $delivery_id = $edit_id;
            } else {
                // Insert new delivery record
                $delivery_sql = "
                    INSERT INTO fuel_deliveries (po_id, delivery_date, delivery_reference, received_by, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ";
                
                $stmt = $conn->prepare($delivery_sql);
                $stmt->bind_param("ississ", $order_id, $delivery_date, $delivery_reference, $_SESSION['user_id'], $delivery_status, $delivery_notes);
                $stmt->execute();
                
                $delivery_id = $conn->insert_id;
            }
            
            // Insert delivery items for each tank entry
            $delivery_item_sql = "
                INSERT INTO delivery_items (delivery_id, fuel_type_id, tank_id, quantity_ordered, quantity_received, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $conn->prepare($delivery_item_sql);
            
            foreach ($items_data as $po_item_id => $item) {
                 $fuel_type_id = $item['fuel_type_id'];
                 $ordered_qty_for_item = $item['ordered_qty']; // Use the specific item's ordered quantity
                
                // Process each tank allocation for this item
                foreach ($item['tanks'] as $tank) {
                    $tank_id = $tank['tank_id'];
                    $quantity = $tank['quantity'];
                    $tank_notes = $tank['notes'];
                    
                    // Skip invalid allocations (quantity 0 is now allowed for partial deliveries, but tank_id must be valid)
                    if ($tank_id <= 0) {
                        continue; 
                    }
                     // Only insert if quantity > 0
                    if ($quantity > 0) {
                        // Insert delivery item
                        $stmt->bind_param("iiidds", $delivery_id, $fuel_type_id, $tank_id, $ordered_qty_for_item, $quantity, $tank_notes);
                        $stmt->execute();

                        // Update tank inventory
                        if ($is_edit) {
                            // For edits, adjust by the difference from previous quantity
                            $prev_qty = isset($previous_tank_quantities[$tank_id]) ? $previous_tank_quantities[$tank_id] : 0;
                            $quantity_diff = $quantity - $prev_qty;
                            
                            // Only update tank if there's a change in quantity
                            if (abs($quantity_diff) > 0.001) {
                                // Get current tank volume
                                $tank_query = "SELECT current_volume FROM tanks WHERE tank_id = ?";
                                $tank_stmt = $conn->prepare($tank_query);
                                $tank_stmt->bind_param("i", $tank_id);
                                $tank_stmt->execute();
                                $tank_result = $tank_stmt->get_result();
                                
                                if ($tank_result->num_rows === 0) {
                                    throw new Exception("Tank with ID $tank_id not found.");
                                }
                                
                                $tank_data = $tank_result->fetch_assoc();
                                $current_volume = floatval($tank_data['current_volume']);
                                $new_volume = $current_volume + $quantity_diff;
                                
                                // Update tank volume
                                $update_tank_sql = "UPDATE tanks SET current_volume = ? WHERE tank_id = ?";
                                $update_stmt = $conn->prepare($update_tank_sql);
                                $update_stmt->bind_param("di", $new_volume, $tank_id);
                                $update_stmt->execute();
                                
                                // Record tank inventory change
                                $inventory_sql = "
                                    INSERT INTO tank_inventory 
                                    (tank_id, operation_type, reference_id, previous_volume, change_amount, new_volume, operation_date, recorded_by, notes)
                                    VALUES (?, 'delivery', ?, ?, ?, ?, ?, ?, ?)
                                ";
                                
                                $inv_stmt = $conn->prepare($inventory_sql);
                                $inv_stmt->bind_param("iidddsss", $tank_id, $delivery_id, $current_volume, $quantity_diff, $new_volume, $delivery_date, $_SESSION['user_id'], $tank_notes);
                                $inv_stmt->execute();
                            }
                        } else {
                            // For new deliveries, add the full quantity
                            // 1. Get current tank volume
                            $tank_query = "SELECT current_volume FROM tanks WHERE tank_id = ?";
                            $tank_stmt = $conn->prepare($tank_query);
                            $tank_stmt->bind_param("i", $tank_id);
                            $tank_stmt->execute();
                            $tank_result = $tank_stmt->get_result();

                            if ($tank_result->num_rows === 0) {
                                throw new Exception("Tank with ID $tank_id not found.");
                            }

                            $tank_data = $tank_result->fetch_assoc();
                            $previous_volume = floatval($tank_data['current_volume']);
                            $new_volume = $previous_volume + $quantity;

                            // 2. Update tank volume
                            $update_tank_sql = "UPDATE tanks SET current_volume = ? WHERE tank_id = ?";
                            $update_stmt = $conn->prepare($update_tank_sql);
                            $update_stmt->bind_param("di", $new_volume, $tank_id);
                            $update_stmt->execute();

                            // 3. Record tank inventory change
                            $inventory_sql = "
                                INSERT INTO tank_inventory 
                                (tank_id, operation_type, reference_id, previous_volume, change_amount, new_volume, operation_date, recorded_by, notes)
                                VALUES (?, 'delivery', ?, ?, ?, ?, ?, ?, ?)
                            ";

                            $inv_stmt = $conn->prepare($inventory_sql);
                            $inv_stmt->bind_param("iiddisss", $tank_id, $delivery_id, $previous_volume, $quantity, $new_volume, $delivery_date, $_SESSION['user_id'], $tank_notes);
                            $inv_stmt->execute();
                        }
                    } // End if quantity > 0
                } // End foreach tank
            } // End foreach item
            
            // Update purchase order status based on delivery completion
            $po_status = $complete_delivery ? 'delivered' : 'in_progress';
            $po_sql = "UPDATE purchase_orders SET status = ? WHERE po_id = ?";
            $po_stmt = $conn->prepare($po_sql);
            $po_stmt->bind_param("si", $po_status, $order_id);
            
            if (!$po_stmt->execute()) {
                throw new Exception("Error updating purchase order status: " . $po_stmt->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            if ($is_edit) {
                $delivery_message = "Delivery updated successfully. " . ($complete_delivery ? "All" : "Some") . " items were received.";
            } else {
                $delivery_message = "Delivery recorded successfully. " . ($complete_delivery ? "All" : "Some") . " items were received.";
            }
            
            // Redirect to refresh the page and show updated data
            header("Location: order_details.php?id=$order_id&" . ($is_edit ? "edit_success=1" : "delivery_success=1"));
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $delivery_error = "Error " . ($is_edit ? "updating" : "recording") . " delivery: " . $e->getMessage();
            $show_delivery_form = true;
        }
    }
}

if (isset($_GET['cancel_success'])) {
    $delivery_message = "Order cancelled successfully.";
}

// Check for success message
if (isset($_GET['delivery_success'])) {
    $delivery_message = "Delivery recorded successfully.";
}

if (isset($_GET['edit_success'])) {
    $delivery_message = "Delivery updated successfully.";
}

if (isset($_GET['status_change_success'])) {
    $delivery_message = "Order status updated successfully.";
}

if (isset($_GET['success']) && $_GET['success'] === 'updated') {
    $delivery_message = "Order updated successfully.";
}

// Get tanks for delivery form
$tanks_query = "
    SELECT t.tank_id, t.tank_name, t.fuel_type_id, t.capacity, t.current_volume,
           ft.fuel_name
    FROM tanks t
    JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
    WHERE t.status = 'active'
    ORDER BY ft.fuel_name, t.tank_name
";
$tanks_result = $conn->query($tanks_query);
$tanks_by_fuel_type = [];

if ($tanks_result) {
    while ($tank = $tanks_result->fetch_assoc()) {
        if (!isset($tanks_by_fuel_type[$tank['fuel_type_id']])) {
            $tanks_by_fuel_type[$tank['fuel_type_id']] = [];
        }
        $tanks_by_fuel_type[$tank['fuel_type_id']][] = $tank;
    }
}

// Status colors for badges
$status_classes = [
    'draft' => 'bg-gray-100 text-gray-800',
    'submitted' => 'bg-blue-100 text-blue-800',
    'approved' => 'bg-green-100 text-green-800',
    'in_progress' => 'bg-yellow-100 text-yellow-800',
    'delivered' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-red-100 text-red-800'
];

$payment_classes = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'partial' => 'bg-blue-100 text-blue-800',
    'paid' => 'bg-green-100 text-green-800'
];

$delivery_status_classes = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'partial' => 'bg-blue-100 text-blue-800',
    'complete' => 'bg-green-100 text-green-800'
];
?>

<div class="container mx-auto px-4 py-4">
    
    <?php if (!empty($delivery_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($delivery_message) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($delivery_error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Error:</p>
        <p><?= htmlspecialchars($delivery_error) ?></p>
    </div>
    <?php endif; ?>
    
    
    <div class="mb-6 flex flex-wrap gap-3">
        <a href="view_orders.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
            <i class="fas fa-arrow-left mr-2"></i> Back to Orders
        </a>
        
        <?php if (in_array($order['status'], ['draft', 'submitted']) && in_array($user_data['role'], ['admin', 'manager'])): ?>
        <a href="update_order.php?id=<?= $order_id ?>" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-opacity-50">
            <i class="fas fa-edit mr-2"></i> Edit Order
        </a>
        <?php endif; ?>
        
        <?php if ($order['status'] === 'approved' && in_array($user_data['role'], ['admin', 'manager'])): ?>
        <a href="?id=<?= $order_id ?>&action=delivery" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
            <i class="fas fa-truck mr-2"></i> Record Delivery
        </a>
        <?php endif; ?>

        <?php if ($order['status'] !== 'cancelled' && in_array($user_data['role'], ['admin', 'manager'])): ?>
        <a href="process_payment.php?id=<?= $order_id ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
            <i class="fas fa-money-bill-wave mr-2"></i> Process Payment
        </a>
        <?php endif; ?>
        
        <?php if ($order['status'] === 'in_progress' && in_array($user_data['role'], ['admin', 'manager'])): ?>
        <a href="?id=<?= $order_id ?>&action=change_status&new_status=approved" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-opacity-50">
            <i class="fas fa-check-circle mr-2"></i> Change to Approved
        </a>
        <?php endif; ?>
        
        <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered' && $user_data['role'] === 'admin'): ?>
        <a href="?id=<?= $order_id ?>&action=cancel" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50"
           onclick="return confirm('Are you sure you want to cancel this order? This action cannot be undone.')">
            <i class="fas fa-times-circle mr-2"></i> Cancel Order
        </a>
        <?php endif; ?>

        
        <a href="#" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50"
           onclick="window.print()">
            <i class="fas fa-print mr-2"></i> Print Order
        </a>
    </div>

    
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Information</h2>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">PO Number:</span>
                    <span class="font-medium"><?= htmlspecialchars($order['po_number']) ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="px-2 py-1 text-xs rounded-full <?= $status_classes[$order['status']] ?? '' ?>">
                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Order Date:</span>
                    <span><?= date('M d, Y', strtotime($order['order_date'])) ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Expected Delivery:</span>
                    <span><?= $order['expected_delivery_date'] ? date('M d, Y', strtotime($order['expected_delivery_date'])) : 'Not set' ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Amount:</span>
                    <span class="font-medium"><?= $currency_symbol ?><?= number_format($order['total_amount'] ?? 0, 2) ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment Status:</span>
                    <span class="px-2 py-1 text-xs rounded-full <?= $payment_classes[$order['payment_status']] ?? '' ?>">
                        <?= ucfirst($order['payment_status']) ?>
                    </span>
                </div>
                
                <?php if ($order['payment_date']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment Date:</span>
                    <span><?= date('M d, Y', strtotime($order['payment_date'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($order['payment_reference']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment Reference:</span>
                    <span><?= htmlspecialchars($order['payment_reference']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Created By:</span>
                    <span><?= htmlspecialchars($order['created_by']) ?></span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Created On:</span>
                    <span><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                
                <?php if ($order['updated_at'] && $order['updated_at'] !== $order['created_at']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Last Updated:</span>
                    <span><?= date('M d, Y H:i', strtotime($order['updated_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($order['notes'])): ?>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Notes:</h3>
                <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Supplier Information</h2>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Supplier Name:</span>
                    <span class="font-medium"><?= htmlspecialchars($order['supplier_name']) ?></span>
                </div>
                
                <?php if ($order['contact_person']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Contact Person:</span>
                    <span><?= htmlspecialchars($order['contact_person']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Phone:</span>
                    <span><?= htmlspecialchars($order['phone']) ?></span>
                </div>
                
                <?php if ($order['email']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Email:</span>
                    <span class="text-blue-600"><?= htmlspecialchars($order['email']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Delivery Status</h2>
            
            <?php if ($delivery_result && $delivery_result->num_rows > 0): ?>
                <?php $delivery = $delivery_result->fetch_assoc(); ?>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Delivery Date:</span>
                        <span><?= date('M d, Y', strtotime($delivery['delivery_date'])) ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-2 py-1 text-xs rounded-full <?= $delivery_status_classes[$delivery['delivery_status']] ?? '' ?>">
                            <?= ucfirst($delivery['delivery_status']) ?>
                        </span>
                    </div>
                    
                    <?php if ($delivery['delivery_reference']): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Reference:</span>
                        <span><?= htmlspecialchars($delivery['delivery_reference']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Received By:</span>
                        <span><?= htmlspecialchars($delivery['received_by']) ?></span>
                    </div>
                    
                    <?php if ($delivery['delivery_notes']): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Delivery Notes:</h3>
                        <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($delivery['delivery_notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_data['role'], ['admin', 'manager'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="?id=<?= $order_id ?>&action=edit_delivery&delivery_id=<?= $delivery['delivery_id'] ?>" class="inline-flex items-center px-4 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-opacity-50">
                            <i class="fas fa-edit mr-2"></i> Edit Delivery
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No delivery information yet.</p>
                    
                    <?php if ($order['status'] === 'approved' && in_array($user_data['role'], ['admin', 'manager'])): ?>
                    <a href="?id=<?= $order_id ?>&action=delivery" class="inline-flex items-center px-4 py-2 mt-3 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                        <i class="fas fa-truck mr-2"></i> Record Delivery
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-800">Order Items</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (L)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Value</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                     <?php 
                     $item_counter = 1;
                     if ($items_result && $items_result->num_rows > 0): 
                        $items_result->data_seek(0); // Reset result pointer 
                        while ($item = $items_result->fetch_assoc()): 
                     ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?= $item_counter++ ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($item['fuel_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($item['quantity'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right"><?= $currency_symbol ?><?= number_format($item['unit_price'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium"><?= $currency_symbol ?><?= number_format($item['invoice_value'] ?? 0, 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No items found for this order.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-right font-semibold">Total:</td>
                        <td class="px-6 py-4 text-right font-bold"><?= $currency_symbol ?><?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <?php if ($delivery_result && $delivery_result->num_rows > 0): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-800">Delivery Details</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tank</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ordered Qty (L)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Received Qty (L)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Difference</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    // Reset result pointer
                    $delivery_result->data_seek(0);
                    // Fetch the first (most recent) delivery record to get its ID
                    $delivery = $delivery_result->fetch_assoc(); 
                    
                    if($delivery) { // Check if a delivery record was fetched
                         // Get delivery items associated with this delivery ID
                        $delivery_items_query = "
                            SELECT di.delivery_item_id, di.fuel_type_id, di.tank_id, di.quantity_ordered, 
                                   di.quantity_received, di.notes, ft.fuel_name, t.tank_name
                            FROM delivery_items di
                            JOIN fuel_types ft ON di.fuel_type_id = ft.fuel_type_id
                            JOIN tanks t ON di.tank_id = t.tank_id
                            WHERE di.delivery_id = ?
                            ORDER BY ft.fuel_name, t.tank_name
                        ";
                        
                        $stmt_items = $conn->prepare($delivery_items_query);
                        $stmt_items->bind_param("i", $delivery['delivery_id']);
                        $stmt_items->execute();
                        $delivery_items_result = $stmt_items->get_result();
                        
                        if ($delivery_items_result && $delivery_items_result->num_rows > 0):
                            // Fetch all delivery items and store them
                            $all_items = [];
                            while ($item = $delivery_items_result->fetch_assoc()) {
                                $all_items[] = $item;
                            }
                            
                            // Group delivery items by fuel type
                            $grouped_items = [];
                            foreach ($all_items as $item) {
                                $fuel_id = $item['fuel_type_id'];
                                if (!isset($grouped_items[$fuel_id])) {
                                    $grouped_items[$fuel_id] = [
                                        'fuel_name' => $item['fuel_name'],
                                        // Find the corresponding ordered quantity for this fuel type from the PO items
                                        'quantity_ordered' => 0, // Default
                                        'tanks' => []
                                    ];
                                     // Find the original ordered qty for this fuel type ID
                                    $items_result->data_seek(0); // Reset PO items pointer
                                    while($po_item = $items_result->fetch_assoc()){
                                        if($po_item['fuel_type_id'] == $fuel_id){
                                             $grouped_items[$fuel_id]['quantity_ordered'] = $po_item['quantity'];
                                             break; // Found it, no need to loop further for this fuel type
                                        }
                                    }
                                }
                                $grouped_items[$fuel_id]['tanks'][] = [
                                    'tank_name' => $item['tank_name'],
                                    'quantity_received' => $item['quantity_received']
                                ];
                            }
                            
                            // Display each fuel type and its tanks
                            foreach ($grouped_items as $fuel_id => $fuel_data):
                                $total_received = 0;
                                foreach ($fuel_data['tanks'] as $tank) {
                                    $total_received += $tank['quantity_received'];
                                }
                                
                                $difference = $total_received - $fuel_data['quantity_ordered'];
                                $difference_class = $difference < -0.001 ? 'text-red-600' : ($difference > 0.001 ? 'text-orange-600' : 'text-green-600'); // Added tolerance
                                
                                // Display the first tank with fuel details
                                $first_tank = reset($fuel_data['tanks']);
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($fuel_data['fuel_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($first_tank['tank_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($fuel_data['quantity_ordered'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($first_tank['quantity_received'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right <?= $difference_class ?>">
                                    <?= $difference >= 0 ? '+' : '' ?><?= number_format($difference, 2) ?>
                                </td>
                            </tr>
                            
                            <?php
                                // Display additional tanks if any
                                $other_tanks = array_slice($fuel_data['tanks'], 1);
                                foreach ($other_tanks as $tank):
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($tank['tank_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"><?= number_format($tank['quantity_received'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right"></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($fuel_data['tanks']) > 1): ?>
                            <tr class="bg-gray-50">
                                <td class="px-6 py-3 whitespace-nowrap font-medium" colspan="2">Total for <?= htmlspecialchars($fuel_data['fuel_name']) ?></td>
                                <td class="px-6 py-3 whitespace-nowrap text-right font-medium"><?= number_format($fuel_data['quantity_ordered'], 2) ?></td>
                                <td class="px-6 py-3 whitespace-nowrap text-right font-medium"><?= number_format($total_received, 2) ?></td>
                                <td class="px-6 py-3 whitespace-nowrap text-right font-medium <?= $difference_class ?>">
                                    <?= $difference >= 0 ? '+' : '' ?><?= number_format($difference, 2) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                        <?php endforeach; // End grouped_items loop ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No delivery items found for this delivery record.</td>
                            </tr>
                        <?php endif; // End delivery_items_result check
                    } else { // End if $delivery exists
                         // This case should ideally not happen if the outer check ($delivery_result && $delivery_result->num_rows > 0) passed
                         echo '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Could not load delivery details.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($show_delivery_form) && $show_delivery_form): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <?= isset($edit_delivery_id) && $edit_delivery_id > 0 ? 'Edit Delivery' : 'Record Delivery' ?>
        </h2>
        
        <form action="" method="POST" id="deliveryForm">
            <input type="hidden" name="action" value="<?= isset($edit_delivery_id) && $edit_delivery_id > 0 ? 'update_delivery' : 'record_delivery' ?>">
            <?php if (isset($edit_delivery_id) && $edit_delivery_id > 0): ?>
            <input type="hidden" name="delivery_id" value="<?= $edit_delivery_id ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="delivery_date" class="block text-sm font-medium text-gray-700 mb-1">Delivery Date <span class="text-red-500">*</span></label>
                    <input type="date" id="delivery_date" name="delivery_date" value="<?= isset($delivery_data) ? $delivery_data['delivery_date'] : date('Y-m-d') ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>
                
                <div>
                    <label for="delivery_reference" class="block text-sm font-medium text-gray-700 mb-1">Delivery Reference</label>
                    <input type="text" id="delivery_reference" name="delivery_reference" value="<?= isset($delivery_data) ? htmlspecialchars($delivery_data['delivery_reference']) : '' ?>" placeholder="Delivery note number, etc." class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="delivery_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="delivery_notes" name="delivery_notes" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= isset($delivery_data) ? htmlspecialchars($delivery_data['notes']) : '' ?></textarea>
            </div>
            
            <div class="mb-4">
                <h3 class="text-md font-medium text-gray-800 mb-2">Items Received</h3>
                
                <div id="delivery-items-container">
                    <?php 
                    $item_index = 0; // Initialize counter here to fix the undefined variable warning
                    if ($items_result && $items_result->num_rows > 0): 
                        $items_result->data_seek(0); // Reset result pointer
                        while ($item = $items_result->fetch_assoc()): 
                            // Calculate already received quantity for this PO item across all previous deliveries
                            // excluding the current delivery being edited
                            $already_received_sql = "SELECT SUM(di.quantity_received) as total_received 
                                                    FROM delivery_items di
                                                    JOIN fuel_deliveries fd ON di.delivery_id = fd.delivery_id
                                                    WHERE fd.po_id = ? AND di.fuel_type_id = ?";
                            
                            if (isset($edit_delivery_id) && $edit_delivery_id > 0) {
                                $already_received_sql .= " AND fd.delivery_id != ?";
                            }
                            
                            $stmt_received = $conn->prepare($already_received_sql);
                            
                            if (isset($edit_delivery_id) && $edit_delivery_id > 0) {
                                $stmt_received->bind_param("iii", $order_id, $item['fuel_type_id'], $edit_delivery_id);
                            } else {
                                $stmt_received->bind_param("ii", $order_id, $item['fuel_type_id']);
                            }
                            
                            $stmt_received->execute();
                            $received_result = $stmt_received->get_result();
                            $already_received_data = $received_result->fetch_assoc();
                            $already_received_qty = $already_received_data['total_received'] ?? 0;
                            $remaining_to_receive = max(0, $item['quantity'] - $already_received_qty);
                    ?>
                            <div class="fuel-item mb-8" data-item-id="<?= $item['po_item_id'] ?>" data-fuel-type-id="<?= $item['fuel_type_id'] ?>" data-ordered-qty="<?= $item['quantity'] ?>" data-remaining-qty="<?= $remaining_to_receive ?>">
                                <div class="border-b border-gray-200 pb-3 mb-3">
                                    <div class="flex justify-between items-center">
                                        <h4 class="font-medium text-gray-800">
                                            <?= htmlspecialchars($item['fuel_name']) ?> 
                                            <span class="text-sm text-gray-500">
                                                (Ordered: <?= number_format($item['quantity'], 2) ?> L, 
                                                 Received: <?= number_format($already_received_qty, 2) ?> L,
                                                 Remaining: <?= number_format($remaining_to_receive, 2) ?> L)
                                            </span>
                                        </h4>
                                        <div>
                                            <button type="button" class="add-tank-btn px-3 py-1 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" data-item-id="<?= $item['po_item_id'] ?>">
                                                <i class="fas fa-plus mr-1"></i> Add Tank
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tank-entries" data-item-id="<?= $item['po_item_id'] ?>">
                                    <?php
                                    // For edits, look up existing delivery items for this fuel type
                                    $has_existing_entries = false;
                                    
                                    if (isset($edit_delivery_id) && $edit_delivery_id > 0 && isset($delivery_items)) {
                                        $fuel_entries = array_filter($delivery_items, function($entry) use ($item) {
                                            return $entry['fuel_type_id'] == $item['fuel_type_id'];
                                        });
                                        
                                        if (!empty($fuel_entries)) {
                                            $has_existing_entries = true;
                                            foreach ($fuel_entries as $entry) {
                                    ?>
                                    <div class="tank-entry mb-3 pl-4 border-l-2 border-blue-200">
                                        <div class="grid grid-cols-12 gap-2">
                                            <div class="col-span-6">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Destination Tank <span class="text-red-500">*</span></label>
                                                <select name="item_entries[<?= $item_index ?>][tank_id]" class="tank-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                                    <option value="">Select Tank</option>
                                                    <?php if (isset($tanks_by_fuel_type[$item['fuel_type_id']])): ?>
                                                        <?php foreach ($tanks_by_fuel_type[$item['fuel_type_id']] as $tank): ?>
                                                            <?php 
                                                            $available_capacity = $tank['capacity'] - $tank['current_volume'];
                                                            if ($tank['tank_id'] == $entry['tank_id']) {
                                                                // Add back the quantity for this tank that was previously delivered
                                                                $available_capacity += floatval($entry['quantity_received']);
                                                            }
                                                            $capacity_info = "(" . number_format($tank['current_volume'], 0) . "/" . number_format($tank['capacity'], 0) . "L - " . number_format($available_capacity, 0) . "L available)";
                                                            ?>
                                                            <option value="<?= $tank['tank_id'] ?>" 
                                                                    data-available="<?= $available_capacity ?>" 
                                                                    <?= $tank['tank_id'] == $entry['tank_id'] ? 'selected' : '' ?>
                                                                    <?= $available_capacity < 0.01 ? 'data-warning="true"' : '' ?>>
                                                                <?= htmlspecialchars($tank['tank_name']) ?> <?= $capacity_info ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="" disabled>No compatible tanks found</option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden" name="item_entries[<?= $item_index ?>][po_item_id]" value="<?= $item['po_item_id'] ?>">
                                            </div>
                                            
                                            <div class="col-span-5">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Received Now (L) <span class="text-red-500">*</span></label>
                                                <input type="number" name="item_entries[<?= $item_index ?>][quantity]" min="0" step="0.01" 
                                                       value="<?= number_format($entry['quantity_received'], 2, '.', '') ?>" 
                                                       class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            </div>
                                            
                                            <div class="col-span-1 flex items-end pb-1">
                                                <button type="button" class="remove-tank-btn ml-1 text-red-500 hover:text-red-700 focus:outline-none">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="notes-container mt-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                            <textarea name="item_entries[<?= $item_index ?>][notes]" rows="1" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= htmlspecialchars($entry['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <?php
                                                $item_index++;
                                            }
                                        }
                                    }
                                    
                                    // If no existing entries or not editing, show default entry
                                    if (!$has_existing_entries):
                                    ?>
                                    <div class="tank-entry mb-3 pl-4 border-l-2 border-blue-200">
                                        <div class="grid grid-cols-12 gap-2">
                                            <div class="col-span-6">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Destination Tank <span class="text-red-500">*</span></label>
                                                <select name="item_entries[<?= $item_index ?>][tank_id]" class="tank-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                                    <option value="">Select Tank</option>
                                                    <?php if (isset($tanks_by_fuel_type[$item['fuel_type_id']])): ?>
                                                        <?php foreach ($tanks_by_fuel_type[$item['fuel_type_id']] as $tank): ?>
                                                            <?php 
                                                            $available_capacity = $tank['capacity'] - $tank['current_volume'];
                                                            $capacity_info = "(" . number_format($tank['current_volume'], 0) . "/" . number_format($tank['capacity'], 0) . "L - " . number_format($available_capacity, 0) . "L available)";
                                                            ?>
                                                            <option value="<?= $tank['tank_id'] ?>" data-available="<?= $available_capacity ?>" <?= $available_capacity < 0.01 ? 'data-warning="true"' : '' ?>>
                                                                <?= htmlspecialchars($tank['tank_name']) ?> <?= $capacity_info ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="" disabled>No compatible tanks found</option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden" name="item_entries[<?= $item_index ?>][po_item_id]" value="<?= $item['po_item_id'] ?>">
                                            </div>
                                            
                                            <div class="col-span-5">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Received Now (L) <span class="text-red-500">*</span></label>
                                                <input type="number" name="item_entries[<?= $item_index ?>][quantity]" min="0" step="0.01" value="<?= number_format($remaining_to_receive, 2, '.', '') ?>" class="quantity-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            </div>
                                            
                                            <div class="col-span-1 flex items-end pb-1">
                                                <button type="button" class="remove-tank-btn ml-1 text-red-500 hover:text-red-700 focus:outline-none" style="display: none;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="notes-container mt-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                            <textarea name="item_entries[<?= $item_index ?>][notes]" rows="1" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                                        </div>
                                    </div>
                                    <?php 
                                        $item_index++;
                                    endif; 
                                    ?>
                                </div>
                                <div class="remaining-info mt-2 text-right text-sm">
                                    <span class="font-medium">Remaining to distribute for this item in this delivery: </span>
                                    <span class="remaining-qty font-bold text-blue-600"><?= number_format($remaining_to_receive, 2) ?></span> L
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500">No items to receive</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <a href="order_details.php?id=<?= $order_id ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                    <?= isset($edit_delivery_id) && $edit_delivery_id > 0 ? 'Update Delivery' : 'Record Delivery' ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php

// Add JavaScript for tank warning displays and dynamic entry handling
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Global entry counter for new entries
    let entryCounter = ' . $item_index . ';

    // Add tank button functionality
    const addTankButtons = document.querySelectorAll(".add-tank-btn");
    addTankButtons.forEach(button => {
        button.addEventListener("click", function() {
            const itemId = this.dataset.itemId;
            const fuelItem = document.querySelector(`.fuel-item[data-item-id="${itemId}"]`);
            const tankEntries = fuelItem.querySelector(`.tank-entries[data-item-id="${itemId}"]`);
            const fuelTypeId = fuelItem.dataset.fuelTypeId;

            // Clone the first entry template within this fuel item
            const firstEntry = tankEntries.querySelector(".tank-entry");
            if (!firstEntry) return; // Should not happen, but safety check
            const newEntry = firstEntry.cloneNode(true);

            // Update name attributes for new entry using the global counter
            const inputs = newEntry.querySelectorAll("input, select, textarea");
            inputs.forEach(input => {
                if (input.name) {
                    // Update the index in name attribute
                    input.name = input.name.replace(/item_entries\[\d+\]/, `item_entries[${entryCounter}]`);
                }

                // Clear value except for hidden fields and default quantity to 0 for new rows
                 if (input.type !== "hidden") {
                    if(input.classList.contains(\'quantity-input\')) { // Escaped single quotes
                         input.value = "0.00"; // Default new splits to 0
                    } else {
                         input.value = "";
                    }
                }
                 if (input.tagName === \'SELECT\') { // Escaped single quotes
                    input.selectedIndex = 0; // Reset select to the first option ("Select Tank")
                }
            });

            // Show remove button
            const removeButton = newEntry.querySelector(".remove-tank-btn");
            removeButton.style.display = "inline-block"; // Make it visible
            removeButton.addEventListener("click", removeTankEntry);

            // Add event listeners for validation to the new row
            const quantityInput = newEntry.querySelector(".quantity-input");
            quantityInput.addEventListener("input", function() { updateRemainingQuantity.call(fuelItem); }); // Pass fuelItem context

            const tankSelect = newEntry.querySelector(".tank-select");
            tankSelect.addEventListener("change", checkTankCapacity);

            // Append the new entry
            tankEntries.appendChild(newEntry);
            entryCounter++; // Increment global counter for the next new entry across all items

            // Ensure all remove buttons within this item are visible now
            tankEntries.querySelectorAll(".remove-tank-btn").forEach(btn => {
                 btn.style.display = "inline-block";
            });
             // Hide remove button if only one entry left after potential removal
            if (tankEntries.querySelectorAll(".tank-entry").length === 1) {
                 tankEntries.querySelector(".remove-tank-btn").style.display = \'none\'; // Escaped single quotes
            }

            // Update remaining quantity for this specific fuel item
            updateRemainingQuantity.call(fuelItem);
        });
    });

    // Function to remove tank entry
    function removeTankEntry() {
        const entry = this.closest(\'.tank-entry\'); // Escaped single quotes
        const entriesContainer = this.closest(\'.tank-entries\'); // Escaped single quotes
        const fuelItem = this.closest(\'.fuel-item\'); // Escaped single quotes

        entry.remove(); // Remove the entry row

        // Hide remove button if only one entry left
        const remainingEntries = entriesContainer.querySelectorAll(".tank-entry");
        if (remainingEntries.length === 1) {
             const removeBtn = remainingEntries[0].querySelector(".remove-tank-btn");
             if(removeBtn) removeBtn.style.display = \'none\'; // Escaped single quotes
        }

        // Update remaining quantity for this fuel item
        updateRemainingQuantity.call(fuelItem);
    }

    // Add event listener to initial remove buttons (if any are initially visible)
    document.querySelectorAll(".remove-tank-btn").forEach(button => {
         // Only add listener if it\'s meant to be clickable initially
         if(button.style.display !== \'none\') { // Escaped single quotes
              button.addEventListener("click", removeTankEntry);
         }
    });

    // Add event listeners to initial quantity inputs
    document.querySelectorAll(".quantity-input").forEach(input => {
         const fuelItem = input.closest(\'.fuel-item\'); // Escaped single quotes
        input.addEventListener("input", function() { updateRemainingQuantity.call(fuelItem); });
    });

    // Add event listeners to initial tank selects
    document.querySelectorAll(".tank-select").forEach(select => {
        select.addEventListener("change", checkTankCapacity);
    });

    // Function to update remaining quantity for the specific fuel item being edited
    function updateRemainingQuantity() {
         // \'this\' should be the fuelItem div, passed using .call()
        const fuelItem = this;
        const remainingForPO = parseFloat(fuelItem.dataset.remainingQty); // Total remaining for this item on the PO
        let totalAllocatedInThisDelivery = 0;

        // Sum up quantities from all tank entries for THIS fuel item in THIS delivery form
        const quantityInputs = fuelItem.querySelectorAll(".quantity-input");
        quantityInputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            totalAllocatedInThisDelivery += value;
        });

        // Calculate and display remaining FOR THIS DELIVERY SUBMISSION
         const remainingForThisDelivery = remainingForPO - totalAllocatedInThisDelivery;
        const remainingElem = fuelItem.querySelector(".remaining-qty");
        remainingElem.textContent = remainingForThisDelivery.toFixed(2);

        // Highlight based on remaining amount FOR THIS DELIVERY SUBMISSION
        if (Math.abs(remainingForThisDelivery) > 0.01) { // Use a small tolerance
            if (remainingForThisDelivery < 0) {
                // Over-allocated for this delivery (more than remaining on PO)
                remainingElem.classList.remove("text-blue-600", "text-green-600");
                remainingElem.classList.add("text-red-600");
            } else {
                // Under-allocated for this delivery
                remainingElem.classList.remove("text-green-600", "text-red-600");
                remainingElem.classList.add("text-blue-600");
            }
        } else {
            // Perfectly allocated for this delivery
            remainingElem.classList.remove("text-blue-600", "text-red-600");
            remainingElem.classList.add("text-green-600");
        }
    }

    // Initialize remaining quantities on page load
    document.querySelectorAll(".fuel-item").forEach(item => {
        updateRemainingQuantity.call(item);
         // Also, hide remove button if only one entry exists initially
        const entries = item.querySelectorAll(".tank-entry");
        if (entries.length === 1) {
            const removeBtn = entries[0].querySelector(".remove-tank-btn");
             if(removeBtn) removeBtn.style.display = \'none\'; // Escaped single quotes
        }
    });

    // Function to check tank capacity (remains mostly the same)
    function checkTankCapacity() {
        const selectedOption = this.options[this.selectedIndex];
        if (!selectedOption || selectedOption.value === "") return;

        const quantityInput = this.closest(\'.tank-entry\').querySelector(".quantity-input"); // Escaped single quotes
        const currentQuantity = parseFloat(quantityInput.value) || 0;

        if (selectedOption.getAttribute("data-warning") === "true") {
            const available = parseFloat(selectedOption.getAttribute("data-available"));
             // Add a small tolerance for capacity checks
            if (currentQuantity > available + 0.01) {
                alert(`Warning: The selected tank might not have enough capacity (${available.toFixed(2)}L available) for the specified quantity (${currentQuantity.toFixed(2)}L). Please verify tank space.`);
                 // Optionally, you could reset the quantity or highlight the field
                 // quantityInput.value = available.toFixed(2);
                 // quantityInput.style.backgroundColor = \'#fef2f2\'; // Light red // Escaped single quotes
            } else {
                 // quantityInput.style.backgroundColor = \'\'; // Escaped single quotes
            }
        } else {
             // quantityInput.style.backgroundColor = \'\'; // Escaped single quotes
        }
    }

    // Form validation on submit
    const deliveryForm = document.getElementById("deliveryForm");
    if (deliveryForm) { // Check if the form exists before adding listener
        deliveryForm.addEventListener("submit", function(e) {
            let isValid = true;
            let errorMessages = []; // Collect all errors
             let totalQuantityReceivedOverall = 0; // Track if anything is received

            // Check each fuel item
            document.querySelectorAll(".fuel-item").forEach(item => {
                 let itemTotalReceived = 0;
                 const remainingForPO = parseFloat(item.dataset.remainingQty);
                 const fuelName = item.querySelector("h4").textContent.split("(")[0].trim();
                 let hasValidEntry = false; // Flag to check if at least one valid entry exists for the item

                 item.querySelectorAll(".tank-entry").forEach(entry => {
                     const tankSelect = entry.querySelector(".tank-select");
                     const quantityInput = entry.querySelector(".quantity-input");
                     const quantity = parseFloat(quantityInput.value) || 0;

                     // Only validate tank if quantity > 0
                     if (quantity > 0) {
                          hasValidEntry = true; // Mark that this item has a received quantity
                          if (!tankSelect.value) {
                               isValid = false;
                               errorMessages.push(`Please select a destination tank for ${fuelName} where quantity is entered.`);
                          }
                     }
                      if (quantity < 0) { // Quantity cannot be negative
                         isValid = false;
                         errorMessages.push(`Quantity cannot be negative for ${fuelName}.`);
                     }
                     itemTotalReceived += quantity;
                      totalQuantityReceivedOverall += quantity; // Add to overall total
                 });

                  // Check if total received for this item exceeds remaining PO qty
                 if (itemTotalReceived > remainingForPO + 0.01) { // Add tolerance
                     isValid = false;
                      errorMessages.push(`Total quantity entered for ${fuelName} (${itemTotalReceived.toFixed(2)}L) exceeds the remaining amount on the PO (${remainingForPO.toFixed(2)}L).`);
                 }
            });

             // Check if *any* quantity was received across all items
             if (totalQuantityReceivedOverall <= 0) {
                 isValid = false;
                 errorMessages.push("You must enter a quantity greater than zero for at least one item/tank combination.");
             }

            if (!isValid) {
                e.preventDefault();
                alert("Please fix the following errors:\n- " + errorMessages.join("\n- "));
            }
        });
    } // End if(deliveryForm)
});
</script>
';

// Include footer
include_once '../../includes/footer.php';

ob_end_flush();
?>