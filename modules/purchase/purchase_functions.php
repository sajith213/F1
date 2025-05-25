<?php

/**
 * Purchase Module Functions
 * 
 * This file contains helper functions for the purchase order module
 */

if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        if (!isset($GLOBALS['conn'])) {
            require_once __DIR__ . '/../../includes/db.php';
        }
        return $GLOBALS['conn'];
    }
}

/**
 * Generate a new purchase order number
 *
 * @return string The generated PO number
 */
function generatePurchaseOrderNumber() {
    $conn = getDbConnection();
    $prefix = 'PO-';
    $currentDate = date('Ymd');
    $nextPoNumber = $prefix . $currentDate . '-001';

    $stmt = $conn->prepare("
        SELECT po_number FROM product_purchase_orders 
        WHERE po_number LIKE ? 
        ORDER BY po_id DESC LIMIT 1
    ");
    $likeParam = $prefix . $currentDate . '%';
    $stmt->bind_param("s", $likeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $lastPo = $row['po_number'];
        $parts = explode('-', $lastPo);
        if (count($parts) == 3 && $parts[1] == $currentDate) {
            $lastNum = (int)substr($parts[2], 0);
            $nextNum = $lastNum + 1;
            $nextPoNumber = $prefix . $currentDate . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        }
    }
    $stmt->close();
    
    return $nextPoNumber;
}

/**
 * Get purchase order details by ID
 *
 * @param int $poId Purchase order ID
 * @return array|null Purchase order data or null if not found
 */
function getPurchaseOrderById($poId) {
    $conn = getDbConnection();
    
    // Get main order details
    $stmt = $conn->prepare("
        SELECT po.*, s.supplier_name, s.contact_person, s.phone, s.email, u.full_name as created_by_name
        FROM product_purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN users u ON po.created_by = u.user_id
        WHERE po.po_id = ?
    ");
    $stmt->bind_param("i", $poId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT pi.*, p.product_name, p.product_code, 
               COALESCE(u.unit_symbol, p.unit) as purchase_unit,
               p.current_stock
        FROM product_purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.product_id
        LEFT JOIN units u ON p.purchase_unit_id = u.unit_id
        WHERE pi.po_id = ?
        ORDER BY pi.item_id
    ");
    $stmt->bind_param("i", $poId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $order['items'] = [];
    while ($row = $result->fetch_assoc()) {
        $order['items'][] = $row;
    }
    $stmt->close();
    
    return $order;
}

/**
 * Create a new purchase order
 *
 * @param array $orderData Order data
 * @param array $items Order items
 * @return int|bool The new order ID if successful, false otherwise
 */
function createPurchaseOrder($orderData, $items) {
    $conn = getDbConnection();
    
    try {
        $conn->begin_transaction();
        
        // Extract order data
        $poNumber = $orderData['po_number'];
        $supplierId = $orderData['supplier_id'];
        $orderDate = $orderData['order_date'];
        $expectedDelivery = $orderData['expected_delivery'] ?? null;
        $status = $orderData['status'];
        $notes = $orderData['notes'] ?? '';
        
        // Calculate total amount
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }
        
        // Insert purchase order
        $stmt = $conn->prepare("
            INSERT INTO product_purchase_orders (
                po_number, supplier_id, order_date, delivery_date, 
                status, total_amount, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $userId = $_SESSION['user_id'];
        $stmt->bind_param(
            "sisssdsi",
            $poNumber, $supplierId, $orderDate, $expectedDelivery, 
            $status, $totalAmount, $notes, $userId
        );
        
        $stmt->execute();
        $poId = $conn->insert_id;
        $stmt->close();
        
        // Insert purchase order items
        $stmt = $conn->prepare("
            INSERT INTO product_purchase_items (
                po_id, product_id, quantity, unit_price
            ) VALUES (?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            $unitPrice = $item['unit_price'];
            
            $stmt->bind_param("iidd", $poId, $productId, $quantity, $unitPrice);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        return $poId;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Error creating purchase order: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update a purchase order
 *
 * @param int $poId Purchase order ID
 * @param array $orderData Updated order data
 * @param array $items Updated order items
 * @return bool True if successful, false otherwise
 */
function updatePurchaseOrder($poId, $orderData, $items) {
    $conn = getDbConnection();
    
    try {
        $conn->begin_transaction();
        
        // Extract order data
        $poNumber = $orderData['po_number'];
        $supplierId = $orderData['supplier_id'];
        $orderDate = $orderData['order_date'];
        $expectedDelivery = $orderData['expected_delivery'] ?? null;
        $status = $orderData['status'];
        $notes = $orderData['notes'] ?? '';
        
        // Calculate total amount
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }
        
        // Update purchase order
        $stmt = $conn->prepare("
            UPDATE product_purchase_orders SET
                po_number = ?, supplier_id = ?, order_date = ?, 
                delivery_date = ?, status = ?, total_amount = ?, 
                notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE po_id = ?
        ");
        
        $stmt->bind_param(
            "sisssdssi",
            $poNumber, $supplierId, $orderDate, $expectedDelivery, 
            $status, $totalAmount, $notes, $poId
        );
        
        $stmt->execute();
        $stmt->close();
        
        // Delete existing items
        $stmt = $conn->prepare("DELETE FROM product_purchase_items WHERE po_id = ?");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $stmt->close();
        
        // Insert updated purchase order items
        $stmt = $conn->prepare("
            INSERT INTO product_purchase_items (
                po_id, product_id, quantity, unit_price
            ) VALUES (?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            $unitPrice = $item['unit_price'];
            
            $stmt->bind_param("iidd", $poId, $productId, $quantity, $unitPrice);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Error updating purchase order: ' . $e->getMessage());
        return false;
    }
}

/**
 * Process purchase order receiving
 *
 * @param int $poId Purchase order ID
 * @param array $receivedItems Received items data
 * @param string $notes Receiving notes
 * @return bool True if successful, false otherwise
 */
function receivePurchaseOrder($poId, $receivedItems, $notes = '') {
    $conn = getDbConnection();

    try {
        $conn->begin_transaction();

        // Get current purchase order details including items
        $order = getPurchaseOrderById($poId); // Ensure this function fetches items as $order['items']
        if (!$order || !isset($order['items'])) {
             throw new Exception("Could not retrieve order details or items for PO ID: " . $poId);
        }
        if ($order['status'] !== 'ordered' && $order['status'] !== 'partial') {
            throw new Exception("Order status ('".$order['status']."') does not allow receiving products.");
        }


        $allItemsFullyReceived = true; // Assume all will be fully received initially

        // Process each item submitted in the form
        foreach ($receivedItems as $itemId => $receivedData) {
            $receivedQty = (float)$receivedData['received_quantity']; // Cast to float

            if ($receivedQty <= 0) {
                continue; // Skip items with no quantity received this time
            }

            // Find the corresponding item details from the fetched order
            $orderItem = null;
            foreach ($order['items'] as $item) {
                if ($item['item_id'] == $itemId) { // Ensure item_id exists in $order['items']
                    $orderItem = $item;
                    break;
                }
            }

            if (!$orderItem) {
                throw new Exception("Invalid or missing item ID in submitted data: " . $itemId);
            }

            // Calculate new total received quantity for this item
            $orderedQuantity = (float)($orderItem['quantity'] ?? 0);
            $previouslyReceived = (float)($orderItem['received_quantity'] ?? 0); // Need 'received_quantity' from getPurchaseOrderById item query
            $newTotalReceived = $previouslyReceived + $receivedQty;


            // Validate quantity - cannot receive more than ordered
            if ($newTotalReceived > $orderedQuantity) {
                 // Log the details for debugging
                error_log("Over-receiving attempt: PO ID=$poId, Item ID=$itemId, Product='{$orderItem['product_name']}', Ordered=$orderedQuantity, Previously Received=$previouslyReceived, Attempted=$receivedQty, New Total=$newTotalReceived");
                throw new Exception("Received quantity (" . $receivedQty . ") exceeds remaining ordered quantity for product '" . ($orderItem['product_name'] ?? 'Unknown') . "'. Previously received: " . $previouslyReceived);
            }


            // --- Update received quantity in product_purchase_items table ---
            $stmtUpdateItem = $conn->prepare("
                UPDATE product_purchase_items
                SET received_quantity = ?
                WHERE item_id = ?
            ");
            if (!$stmtUpdateItem) throw new Exception("Prepare failed (update item): " . $conn->error);
            $stmtUpdateItem->bind_param("di", $newTotalReceived, $itemId);
            if (!$stmtUpdateItem->execute()) throw new Exception("Execute failed (update item): " . $stmtUpdateItem->error);
            $stmtUpdateItem->close();

            // --- Update product stock in products table ---
            $productId = $orderItem['product_id'];

            // Lock the product row for update to prevent race conditions
            $stmtGetStock = $conn->prepare("SELECT current_stock FROM products WHERE product_id = ? FOR UPDATE");
             if (!$stmtGetStock) throw new Exception("Prepare failed (get stock): " . $conn->error);
            $stmtGetStock->bind_param("i", $productId);
            if (!$stmtGetStock->execute()) throw new Exception("Execute failed (get stock): " . $stmtGetStock->error);
            $resultStock = $stmtGetStock->get_result();

            if ($resultStock->num_rows === 0) {
                $stmtGetStock->close(); // Close statement before throwing
                throw new Exception("Product not found in products table: ID " . $productId);
            }
            $currentStock = (float)$resultStock->fetch_assoc()['current_stock'];
            $newStock = $currentStock + $receivedQty;
            $stmtGetStock->close(); // Close statement after fetching


            // Update product stock level
            $stmtUpdateStock = $conn->prepare("
                UPDATE products
                SET current_stock = ?, updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ?
            ");
             if (!$stmtUpdateStock) throw new Exception("Prepare failed (update stock): " . $conn->error);
            $stmtUpdateStock->bind_param("di", $newStock, $productId);
             if (!$stmtUpdateStock->execute()) throw new Exception("Execute failed (update stock): " . $stmtUpdateStock->error);
            $stmtUpdateStock->close();


            // --- Add inventory transaction record ---
            $stmtInventory = $conn->prepare("
                INSERT INTO inventory_transactions (
                    product_id, transaction_type, reference_id, previous_quantity,
                    change_quantity, new_quantity, transaction_date, notes, conducted_by
                ) VALUES (?, 'purchase', ?, ?, ?, ?, NOW(), ?, ?)
            ");
            if (!$stmtInventory) throw new Exception("Prepare failed (inventory log): " . $conn->error);

            $userId = $_SESSION['user_id'] ?? 0; // Default to 0 if not set, though it should be
            $transactionNotes = "Received from PO #" . ($order['po_number'] ?? $poId);
            if (!empty($notes)) {
                $transactionNotes .= " - " . $notes;
            }

            // *** THIS IS THE CORRECTED LINE ***
            $stmtInventory->bind_param(
                "iiddssi", // Corrected type string (7 characters)
                $productId,
                $poId,
                $currentStock,
                $receivedQty,
                $newStock,
                $transactionNotes,
                $userId
            );
            if (!$stmtInventory->execute()) throw new Exception("Execute failed (inventory log): " . $stmtInventory->error);
            $stmtInventory->close();


            // Check if this specific item is now fully received
            if ($newTotalReceived < $orderedQuantity) {
                $allItemsFullyReceived = false; // If any item is not fully received, the whole order isn't
            }
        } // End foreach loop for received items


         // --- Check ALL items in the order to determine final status ---
         // Re-fetch items to ensure we have the latest received_quantity for all
         $stmtCheckAll = $conn->prepare("SELECT quantity, received_quantity FROM product_purchase_items WHERE po_id = ?");
         if (!$stmtCheckAll) throw new Exception("Prepare failed (check all items): " . $conn->error);
         $stmtCheckAll->bind_param("i", $poId);
          if (!$stmtCheckAll->execute()) throw new Exception("Execute failed (check all items): " . $stmtCheckAll->error);
         $resultAllItems = $stmtCheckAll->get_result();

         $stillPartial = false;
         while ($itemRow = $resultAllItems->fetch_assoc()) {
              $itemOrdered = (float)($itemRow['quantity'] ?? 0);
              $itemReceived = (float)($itemRow['received_quantity'] ?? 0);
              if ($itemReceived < $itemOrdered) {
                  $stillPartial = true; // Found an item not fully received
                  break;
              }
         }
         $stmtCheckAll->close();


         // Determine final PO status
         $newStatus = $stillPartial ? 'partial' : 'delivered';


        // Update overall purchase order status
        $stmtUpdateStatus = $conn->prepare("
            UPDATE product_purchase_orders
            SET status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE po_id = ?
        ");
        if (!$stmtUpdateStatus) throw new Exception("Prepare failed (update status): " . $conn->error);
        $stmtUpdateStatus->bind_param("si", $newStatus, $poId);
        if (!$stmtUpdateStatus->execute()) throw new Exception("Execute failed (update status): " . $stmtUpdateStatus->error);
        $stmtUpdateStatus->close();


        // If all operations were successful, commit the transaction
        $conn->commit();
        return true; // Indicate success

    } catch (Exception $e) {
        // If any operation failed, roll back the transaction
        $conn->rollback();
        // Log the detailed error for debugging purposes
        error_log("Error receiving purchase order (PO ID: $poId): " . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
        // Store a user-friendly error message in session (optional)
        $_SESSION['error_message'] = "An error occurred while receiving the order. Details: " . $e->getMessage(); // Provide more details for debugging
        return false; // Indicate failure
    }
} // End function receivePurchaseOrder

/**
 * Get list of purchase orders with optional filtering
 *
 * @param array $filters Optional filters (status, supplier_id, date_from, date_to)
 * @param int $limit Maximum number of records to return (0 for all)
 * @param int $offset Offset for pagination
 * @return array List of purchase orders
 */
function getPurchaseOrders($filters = [], $limit = 0, $offset = 0) {
    $conn = getDbConnection();
    
    $whereConditions = [];
    $params = [];
    $types = "";
    
    // Apply filters
    if (!empty($filters['status'])) {
        $whereConditions[] = "po.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['supplier_id'])) {
        $whereConditions[] = "po.supplier_id = ?";
        $params[] = $filters['supplier_id'];
        $types .= "i";
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "po.order_date >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "po.order_date <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    // Build query
    $sql = "
        SELECT po.*, s.supplier_name, u.full_name as created_by_name
        FROM product_purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN users u ON po.created_by = u.user_id
    ";
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY po.order_date DESC, po.po_id DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    
    return $orders;
}

/**
 * Calculate purchase order statistics
 *
 * @param string $period Period to calculate stats for ('today', 'month', 'year')
 * @return array Statistics
 */
function getPurchaseOrderStats($period = 'month') {
    $conn = getDbConnection();
    
    $stats = [
        'total_orders' => 0,
        'total_amount' => 0,
        'pending_orders' => 0,
        'delivered_orders' => 0
    ];
    
    // Determine date range
    $dateFrom = '';
    $dateTo = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            $dateFrom = date('Y-m-d');
            break;
        case 'month':
            $dateFrom = date('Y-m-01');
            break;
        case 'year':
            $dateFrom = date('Y-01-01');
            break;
    }
    
    // Get total orders and amount
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_amount
        FROM product_purchase_orders
        WHERE order_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $dateFrom, $dateTo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stats['total_orders'] = $row['total_orders'];
        $stats['total_amount'] = $row['total_amount'];
    }
    $stmt->close();
    
    // Get pending orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending_orders
        FROM product_purchase_orders
        WHERE status IN ('ordered', 'partial') AND order_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $dateFrom, $dateTo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stats['pending_orders'] = $row['pending_orders'];
    }
    $stmt->close();
    
    // Get delivered orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as delivered_orders
        FROM product_purchase_orders
        WHERE status = 'delivered' AND order_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $dateFrom, $dateTo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stats['delivered_orders'] = $row['delivered_orders'];
    }
    $stmt->close();
    
    return $stats;
}