<?php
/**
 * Fuel Ordering Module - Core Functions
 * 
 * This file contains all the core functions related to the fuel ordering process
 * including purchase order management, supplier interactions, and delivery tracking.
 */

// Include database connection if not already included
if (!function_exists('mysqli_connect')) {
    require_once '../../includes/db.php';
}

// Include general functions
require_once '../../includes/functions.php';

/**
 * Get all suppliers
 * 
 * @param bool $active_only Whether to get only active suppliers (default true)
 * @return array Array of supplier records
 */
function get_suppliers($active_only = true) {
    global $conn;
    
    $sql = "SELECT * FROM suppliers";
    if ($active_only) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= " ORDER BY supplier_name ASC";
    
    $result = $conn->query($sql);
    $suppliers = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
    }
    
    return $suppliers;
}

/**
 * Get supplier by ID
 * 
 * @param int $supplier_id The supplier ID
 * @return array|null Supplier data or null if not found
 */
function get_supplier_by_id($supplier_id) {
    global $conn;
    
    $supplier_id = intval($supplier_id);
    $sql = "SELECT * FROM suppliers WHERE supplier_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Add new supplier
 * 
 * @param array $supplier_data Supplier data to insert
 * @return bool|int False on failure, new supplier ID on success
 */
function add_supplier($supplier_data) {
    global $conn;
    
    // Validate required fields
    if (empty($supplier_data['supplier_name']) || empty($supplier_data['phone'])) {
        return false;
    }
    
    $supplier_name = trim($supplier_data['supplier_name']);
    $contact_person = trim($supplier_data['contact_person'] ?? '');
    $phone = trim($supplier_data['phone']);
    $email = trim($supplier_data['email'] ?? '');
    $address = trim($supplier_data['address'] ?? '');
    $status = $supplier_data['status'] ?? 'active';
    
    $sql = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, status) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $supplier_name, $contact_person, $phone, $email, $address, $status);
    
    if ($stmt->execute()) {
        return $stmt->insert_id;
    }
    
    return false;
}

/**
 * Update supplier
 * 
 * @param int $supplier_id Supplier ID to update
 * @param array $supplier_data Updated supplier data
 * @return bool True on success, false on failure
 */
function update_supplier($supplier_id, $supplier_data) {
    global $conn;
    
    // Validate required fields
    if (empty($supplier_data['supplier_name']) || empty($supplier_data['phone'])) {
        return false;
    }
    
    $supplier_id = intval($supplier_id);
    $supplier_name = trim($supplier_data['supplier_name']);
    $contact_person = trim($supplier_data['contact_person'] ?? '');
    $phone = trim($supplier_data['phone']);
    $email = trim($supplier_data['email'] ?? '');
    $address = trim($supplier_data['address'] ?? '');
    $status = $supplier_data['status'] ?? 'active';
    
    $sql = "UPDATE suppliers 
            SET supplier_name = ?, 
                contact_person = ?, 
                phone = ?, 
                email = ?, 
                address = ?, 
                status = ? 
            WHERE supplier_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $supplier_name, $contact_person, $phone, $email, $address, $status, $supplier_id);
    
    return $stmt->execute();
}

/**
 * Get all fuel types
 * 
 * @return array Array of fuel type records
 */
function get_fuel_types() {
    global $conn;
    
    $sql = "SELECT * FROM fuel_types ORDER BY fuel_name ASC";
    $result = $conn->query($sql);
    $fuel_types = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $fuel_types[] = $row;
        }
    }
    
    return $fuel_types;
}

/**
 * Get fuel type by ID
 * 
 * @param int $fuel_type_id The fuel type ID
 * @return array|null Fuel type data or null if not found
 */
function get_fuel_type_by_id($fuel_type_id) {
    global $conn;
    
    $fuel_type_id = intval($fuel_type_id);
    $sql = "SELECT * FROM fuel_types WHERE fuel_type_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $fuel_type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get current fuel prices
 * 
 * @return array Array of current fuel prices by fuel type
 */
function get_current_fuel_prices() {
    global $conn;
    
    $sql = "SELECT fp.* 
            FROM fuel_prices fp
            INNER JOIN (
                SELECT fuel_type_id, MAX(effective_date) as max_date
                FROM fuel_prices
                WHERE effective_date <= CURDATE() AND status = 'active'
                GROUP BY fuel_type_id
            ) latest ON fp.fuel_type_id = latest.fuel_type_id AND fp.effective_date = latest.max_date
            ORDER BY fp.fuel_type_id";
    
    $result = $conn->query($sql);
    $prices = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $prices[$row['fuel_type_id']] = $row;
        }
    }
    
    return $prices;
}

/**
 * Generate a new purchase order number
 * 
 * @return string New PO number
 */
function generate_po_number() {
    global $conn;
    
    $year = date('Y');
    $month = date('m');
    
    $po_prefix = "PO-$year$month-";
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
    
    return $po_prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}

/**
 * Create a new purchase order
 * 
 * @param array $po_data Purchase order data
 * @param array $po_items Items for the purchase order
 * @return bool|int False on failure, new PO ID on success
 */
function create_purchase_order($po_data, $po_items) {
    global $conn;
    
    // Validate required fields
    if (empty($po_data['supplier_id']) || empty($po_data['order_date']) || empty($po_items)) {
        return false;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Generate PO number if not provided
        $po_number = isset($po_data['po_number']) ? $po_data['po_number'] : generate_po_number();
        $supplier_id = intval($po_data['supplier_id']);
        $order_date = $po_data['order_date'];
        $expected_delivery_date = !empty($po_data['expected_delivery_date']) ? $po_data['expected_delivery_date'] : null;
        $status = $po_data['status'] ?? 'draft';
        $payment_status = $po_data['payment_status'] ?? 'pending';
        $payment_date = !empty($po_data['payment_date']) ? $po_data['payment_date'] : null;
        $payment_reference = $po_data['payment_reference'] ?? '';
        $notes = $po_data['notes'] ?? '';
        $created_by = intval($po_data['created_by']);
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($po_items as $item) {
            $quantity = floatval($item['quantity']);
            $unit_price = floatval($item['unit_price']);
            $total_amount += $quantity * $unit_price;
        }
        
        // Insert PO header
        $sql = "INSERT INTO purchase_orders 
                (po_number, supplier_id, order_date, expected_delivery_date, status, 
                total_amount, payment_status, payment_date, payment_reference, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssdssssi", $po_number, $supplier_id, $order_date, $expected_delivery_date, 
                          $status, $total_amount, $payment_status, $payment_date, $payment_reference, $notes, $created_by);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating purchase order: " . $stmt->error);
        }
        
        $po_id = $stmt->insert_id;
        
        // Insert PO items
        $sql = "INSERT INTO po_items (po_id, fuel_type_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($po_items as $item) {
            $fuel_type_id = intval($item['fuel_type_id']);
            $quantity = floatval($item['quantity']);
            $unit_price = floatval($item['unit_price']);
            
            if ($quantity <= 0 || $unit_price <= 0) {
                continue; // Skip invalid items
            }
            
            $stmt->bind_param("iddd", $po_id, $fuel_type_id, $quantity, $unit_price);
            
            if (!$stmt->execute()) {
                throw new Exception("Error adding purchase order item: " . $stmt->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        return $po_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Get purchase order by ID
 * 
 * @param int $po_id Purchase order ID
 * @return array|null Purchase order data with items or null if not found
 */
function get_purchase_order_by_id($po_id) {
    global $conn;
    
    $po_id = intval($po_id);
    
    // Get PO header
    $sql = "SELECT po.*, 
                  s.supplier_name, s.contact_person, s.phone, s.email, s.address,
                  u.full_name as created_by_name 
           FROM purchase_orders po
           LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
           LEFT JOIN users u ON po.created_by = u.user_id
           WHERE po.po_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $po_data = $result->fetch_assoc();
    
    // Get PO items
    $sql = "SELECT pi.*, ft.fuel_name 
            FROM po_items pi
            LEFT JOIN fuel_types ft ON pi.fuel_type_id = ft.fuel_type_id
            WHERE pi.po_id = ?
            ORDER BY pi.po_item_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $po_items = [];
    while ($row = $result->fetch_assoc()) {
        $po_items[] = $row;
    }
    
    $po_data['items'] = $po_items;
    
    // Get delivery information
    $sql = "SELECT fd.*, u.full_name as received_by_name 
            FROM fuel_deliveries fd
            LEFT JOIN users u ON fd.received_by = u.user_id
            WHERE fd.po_id = ?
            ORDER BY fd.delivery_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deliveries = [];
    while ($row = $result->fetch_assoc()) {
        $delivery_id = $row['delivery_id'];
        
        // Get delivery items
        $sql = "SELECT di.*, ft.fuel_name, t.tank_name 
                FROM delivery_items di
                LEFT JOIN fuel_types ft ON di.fuel_type_id = ft.fuel_type_id
                LEFT JOIN tanks t ON di.tank_id = t.tank_id
                WHERE di.delivery_id = ?";
        
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param("i", $delivery_id);
        $stmt2->execute();
        $items_result = $stmt2->get_result();
        
        $delivery_items = [];
        while ($item = $items_result->fetch_assoc()) {
            $delivery_items[] = $item;
        }
        
        $row['items'] = $delivery_items;
        $deliveries[] = $row;
    }
    
    $po_data['deliveries'] = $deliveries;
    
    return $po_data;
}

/**
 * Get all purchase orders with filtering and pagination
 * 
 * @param array $filters Optional filter parameters
 * @param int $page Page number (starts from 1)
 * @param int $per_page Number of records per page
 * @return array Array with 'data' (purchase orders) and 'total' (total records)
 */
function get_purchase_orders($filters = [], $page = 1, $per_page = 10) {
    global $conn;
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Process filters
    if (isset($filters['supplier_id']) && intval($filters['supplier_id']) > 0) {
        $where_conditions[] = "po.supplier_id = ?";
        $params[] = intval($filters['supplier_id']);
        $types .= "i";
    }
    
    if (isset($filters['status']) && !empty($filters['status'])) {
        $where_conditions[] = "po.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (isset($filters['payment_status']) && !empty($filters['payment_status'])) {
        $where_conditions[] = "po.payment_status = ?";
        $params[] = $filters['payment_status'];
        $types .= "s";
    }
    
    if (isset($filters['date_from']) && !empty($filters['date_from'])) {
        $where_conditions[] = "po.order_date >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (isset($filters['date_to']) && !empty($filters['date_to'])) {
        $where_conditions[] = "po.order_date <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (isset($filters['search']) && !empty($filters['search'])) {
        $search_term = "%" . $filters['search'] . "%";
        $where_conditions[] = "(po.po_number LIKE ? OR s.supplier_name LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                 FROM purchase_orders po
                 LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                 $where_clause";
    
    $stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_records = $count_row['total'];
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $per_page;
    
    // Get records with pagination
    $sort = isset($filters['sort']) ? $filters['sort'] : 'order_date';
    $order = isset($filters['order']) && strtolower($filters['order']) === 'asc' ? 'ASC' : 'DESC';
    
    $valid_sorts = ['po_number', 'order_date', 'status', 'total_amount'];
    if (!in_array($sort, $valid_sorts)) {
        $sort = 'order_date';
    }
    
    $sql = "SELECT po.*, s.supplier_name, 
                  u.full_name as created_by_name,
                  (SELECT COUNT(*) FROM fuel_deliveries WHERE po_id = po.po_id) as delivery_count
           FROM purchase_orders po
           LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
           LEFT JOIN users u ON po.created_by = u.user_id
           $where_clause
           ORDER BY po.$sort $order
           LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    
    // Add pagination parameters
    $params[] = $offset;
    $params[] = $per_page;
    $types .= "ii";
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchase_orders = [];
    while ($row = $result->fetch_assoc()) {
        $purchase_orders[] = $row;
    }
    
    return [
        'data' => $purchase_orders,
        'total' => $total_records,
        'pages' => ceil($total_records / $per_page)
    ];
}

/**
 * Update purchase order
 * 
 * @param int $po_id Purchase order ID
 * @param array $po_data Updated purchase order data
 * @param array|null $po_items Updated items (if null, items are not updated)
 * @return bool True on success, false on failure
 */
function update_purchase_order($po_id, $po_data, $po_items = null) {
    global $conn;
    
    $po_id = intval($po_id);
    
    // Validate required fields
    if (empty($po_data['supplier_id']) || empty($po_data['order_date'])) {
        return false;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        $supplier_id = intval($po_data['supplier_id']);
        $order_date = $po_data['order_date'];
        $expected_delivery_date = !empty($po_data['expected_delivery_date']) ? $po_data['expected_delivery_date'] : null;
        $status = $po_data['status'] ?? 'draft';
        $payment_status = $po_data['payment_status'] ?? 'pending';
        $payment_date = !empty($po_data['payment_date']) ? $po_data['payment_date'] : null;
        $payment_reference = $po_data['payment_reference'] ?? '';
        $notes = $po_data['notes'] ?? '';
        
        // Calculate total amount if items are provided
        if ($po_items !== null) {
            $total_amount = 0;
            foreach ($po_items as $item) {
                $quantity = floatval($item['quantity']);
                $unit_price = floatval($item['unit_price']);
                $total_amount += $quantity * $unit_price;
            }
        } else {
            // Get current total amount
            $sql = "SELECT total_amount FROM purchase_orders WHERE po_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $po_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $total_amount = $row['total_amount'];
        }
        
        // Update purchase order header
        $sql = "UPDATE purchase_orders 
                SET supplier_id = ?, 
                    order_date = ?, 
                    expected_delivery_date = ?, 
                    status = ?, 
                    total_amount = ?, 
                    payment_status = ?, 
                    payment_date = ?, 
                    payment_reference = ?, 
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE po_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssdsssssi", $supplier_id, $order_date, $expected_delivery_date, 
                          $status, $total_amount, $payment_status, $payment_date, $payment_reference, $notes, $po_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating purchase order: " . $stmt->error);
        }
        
        // Update items if provided
        if ($po_items !== null) {
            // Delete existing items
            $sql = "DELETE FROM po_items WHERE po_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $po_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error deleting existing items: " . $stmt->error);
            }
            
            // Insert new items
            $sql = "INSERT INTO po_items (po_id, fuel_type_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($po_items as $item) {
                $fuel_type_id = intval($item['fuel_type_id']);
                $quantity = floatval($item['quantity']);
                $unit_price = floatval($item['unit_price']);
                
                if ($quantity <= 0 || $unit_price <= 0) {
                    continue; // Skip invalid items
                }
                
                $stmt->bind_param("iddd", $po_id, $fuel_type_id, $quantity, $unit_price);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error adding updated item: " . $stmt->error);
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Record fuel delivery with support for multi-tank allocations
 * 
 * @param array $delivery_data Common delivery data (po_id, date, etc.)
 * @param array $items_data Array of fuel items with their tank allocations
 * @return bool|int False on failure, new delivery ID on success
 */
function record_fuel_delivery($delivery_data, $items_data) {
    global $conn;
    
    // Validate required fields
    if (empty($delivery_data['po_id']) || empty($delivery_data['delivery_date']) || 
        empty($delivery_data['received_by']) || empty($items_data)) {
        return false;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        $po_id = intval($delivery_data['po_id']);
        $delivery_date = $delivery_data['delivery_date'];
        $delivery_reference = $delivery_data['delivery_reference'] ?? '';
        $received_by = intval($delivery_data['received_by']);
        $notes = $delivery_data['notes'] ?? '';
        
        // Determine if this is a complete or partial delivery
        $complete_delivery = true;
        
        foreach ($items_data as $item) {
            $ordered_qty = floatval($item['ordered_qty']);
            $received_qty = floatval($item['received_qty']);
            
            if ($received_qty <= 0) {
                throw new Exception("Quantity received must be greater than zero for all items.");
            }
            
            if ($received_qty > $ordered_qty) {
                throw new Exception("Received quantity cannot exceed ordered quantity.");
            }
            
            if ($received_qty < $ordered_qty) {
                $complete_delivery = false;
            }
        }
        
        // Set delivery status based on completeness
        $status = $complete_delivery ? 'complete' : 'partial';
        
        // Insert delivery header
        $sql = "INSERT INTO fuel_deliveries 
                (po_id, delivery_date, delivery_reference, received_by, status, notes)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ississ", $po_id, $delivery_date, $delivery_reference, $received_by, $status, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception("Error recording fuel delivery: " . $stmt->error);
        }
        
        $delivery_id = $stmt->insert_id;
        
        // Process each item and its tank allocations
        foreach ($items_data as $item) {
            $fuel_type_id = intval($item['fuel_type_id']);
            $ordered_qty = floatval($item['ordered_qty']);
            
            // Process each tank allocation for this item
            foreach ($item['tanks'] as $tank) {
                $tank_id = intval($tank['tank_id']);
                $quantity = floatval($tank['quantity']);
                $tank_notes = $tank['notes'] ?? '';
                
                // Skip invalid allocations
                if ($tank_id <= 0 || $quantity <= 0) {
                    continue;
                }
                
                // Insert delivery item
                $di_sql = "INSERT INTO delivery_items 
                          (delivery_id, fuel_type_id, tank_id, quantity_ordered, quantity_received, notes)
                          VALUES (?, ?, ?, ?, ?, ?)";
                
                $di_stmt = $conn->prepare($di_sql);
                $di_stmt->bind_param("iiidds", $delivery_id, $fuel_type_id, $tank_id, $ordered_qty, $quantity, $tank_notes);
                
                if (!$di_stmt->execute()) {
                    throw new Exception("Error adding delivery item: " . $di_stmt->error);
                }
                
                // Update tank inventory
                // 1. Get current tank volume
                $tank_sql = "SELECT current_volume FROM tanks WHERE tank_id = ?";
                $tank_stmt = $conn->prepare($tank_sql);
                $tank_stmt->bind_param("i", $tank_id);
                $tank_stmt->execute();
                $tank_result = $tank_stmt->get_result();
                
                if ($tank_result->num_rows === 0) {
                    throw new Exception("Tank not found.");
                }
                
                $tank_row = $tank_result->fetch_assoc();
                $previous_volume = floatval($tank_row['current_volume']);
                $new_volume = $previous_volume + $quantity;
                
                // 2. Update tank volume
                $update_sql = "UPDATE tanks SET current_volume = ? WHERE tank_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("di", $new_volume, $tank_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Error updating tank volume: " . $update_stmt->error);
                }
                
                // 3. Record tank inventory change
                $inventory_sql = "
                    INSERT INTO tank_inventory 
                    (tank_id, operation_type, reference_id, previous_volume, change_amount, new_volume, operation_date, recorded_by, notes)
                    VALUES (?, 'delivery', ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $inv_stmt = $conn->prepare($inventory_sql);
                $inv_stmt->bind_param("iiddisss", $tank_id, $delivery_id, $previous_volume, $quantity, $new_volume, $delivery_date, $received_by, $tank_notes);
                
                if (!$inv_stmt->execute()) {
                    throw new Exception("Error recording tank inventory: " . $inv_stmt->error);
                }
            }
        }
        
        // Update purchase order status based on delivery completion
        $po_status = $complete_delivery ? 'delivered' : 'in_progress';
        $po_sql = "UPDATE purchase_orders SET status = ? WHERE po_id = ?";
        $po_stmt = $conn->prepare($po_sql);
        $po_stmt->bind_param("si", $po_status, $po_id);
        
        if (!$po_stmt->execute()) {
            throw new Exception("Error updating purchase order status: " . $po_stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        return $delivery_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Get tanks by fuel type
 * 
 * @param int $fuel_type_id Fuel type ID
 * @param bool $active_only Whether to get only active tanks (default true)
 * @return array Array of tank records
 */
function get_tanks_by_fuel_type($fuel_type_id, $active_only = true) {
    global $conn;
    
    $fuel_type_id = intval($fuel_type_id);
    
    $sql = "SELECT * FROM tanks WHERE fuel_type_id = ?";
    if ($active_only) {
        $sql .= " AND status = 'active'";
    }
    $sql .= " ORDER BY tank_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $fuel_type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tanks = [];
    while ($row = $result->fetch_assoc()) {
        $tanks[] = $row;
    }
    
    return $tanks;
}

/**
 * Get delivery by ID
 * 
 * @param int $delivery_id Delivery ID
 * @return array|null Delivery data with items or null if not found
 */
function get_delivery_by_id($delivery_id) {
    global $conn;
    
    $delivery_id = intval($delivery_id);
    
    // Get delivery header
    $sql = "SELECT fd.*, po.po_number, po.po_id, u.full_name as received_by_name 
            FROM fuel_deliveries fd
            LEFT JOIN purchase_orders po ON fd.po_id = po.po_id
            LEFT JOIN users u ON fd.received_by = u.user_id
            WHERE fd.delivery_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $delivery_data = $result->fetch_assoc();
    
    // Get delivery items
    $sql = "SELECT di.*, ft.fuel_name, t.tank_name, t.capacity, t.current_volume
            FROM delivery_items di
            LEFT JOIN fuel_types ft ON di.fuel_type_id = ft.fuel_type_id
            LEFT JOIN tanks t ON di.tank_id = t.tank_id
            WHERE di.delivery_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $delivery_items = [];
    while ($row = $result->fetch_assoc()) {
        $delivery_items[] = $row;
    }
    
    $delivery_data['items'] = $delivery_items;
    
    return $delivery_data;
}

/**
 * Get pending purchase orders that need delivery
 * 
 * @return array Array of pending purchase orders
 */
function get_pending_purchase_orders() {
    global $conn;
    
    $sql = "SELECT po.po_id, po.po_number, po.order_date, po.expected_delivery_date, 
                  po.status, po.total_amount, s.supplier_name 
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            WHERE po.status IN ('approved', 'in_progress')
            ORDER BY po.expected_delivery_date, po.order_date";
    
    $result = $conn->query($sql);
    
    $pending_orders = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pending_orders[] = $row;
        }
    }
    
    return $pending_orders;
}

/**
 * Get fuel ordering dashboard data
 * 
 * @return array Dashboard data
 */
function get_fuel_ordering_dashboard_data() {
    global $conn;
    
    $data = [];
    
    // Count of orders by status
    $sql = "SELECT status, COUNT(*) as count 
            FROM purchase_orders 
            GROUP BY status";
    
    $result = $conn->query($sql);
    $status_counts = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $status_counts[$row['status']] = $row['count'];
        }
    }
    
    $data['status_counts'] = $status_counts;
    
    // Count of orders by payment status
    $sql = "SELECT payment_status, COUNT(*) as count 
            FROM purchase_orders 
            GROUP BY payment_status";
    
    $result = $conn->query($sql);
    $payment_status_counts = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $payment_status_counts[$row['payment_status']] = $row['count'];
        }
    }
    
    $data['payment_status_counts'] = $payment_status_counts;
    
    // Recent purchase orders
    $sql = "SELECT po.po_id, po.po_number, po.order_date, po.status, po.total_amount, s.supplier_name 
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            ORDER BY po.order_date DESC, po.po_id DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $recent_orders = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_orders[] = $row;
        }
    }
    
    $data['recent_orders'] = $recent_orders;
    
    // Recent deliveries
    $sql = "SELECT fd.delivery_id, fd.delivery_date, fd.status as delivery_status, 
                  po.po_number, po.po_id, s.supplier_name 
            FROM fuel_deliveries fd
            LEFT JOIN purchase_orders po ON fd.po_id = po.po_id
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            ORDER BY fd.delivery_date DESC, fd.delivery_id DESC
            LIMIT 5";
    
    $result = $conn->query($sql);
    $recent_deliveries = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_deliveries[] = $row;
        }
    }
    
    $data['recent_deliveries'] = $recent_deliveries;
    
    // Low fuel alerts
    $sql = "SELECT t.tank_id, t.tank_name, t.fuel_type_id, t.capacity, t.current_volume, 
                  t.low_level_threshold, ft.fuel_name,
                  (t.current_volume / t.capacity * 100) as percentage 
            FROM tanks t
            JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            WHERE t.status = 'active' AND t.current_volume <= t.low_level_threshold
            ORDER BY percentage ASC";
    
    $result = $conn->query($sql);
    $low_fuel_alerts = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $low_fuel_alerts[] = $row;
        }
    }
    
    $data['low_fuel_alerts'] = $low_fuel_alerts;
    
    return $data;
}

/**
 * Get currency symbol from system settings
 * 
 * @return string Currency symbol (defaults to $ if not found)
 */
function get_currency_symbol() {
    global $conn;
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return '$';
}
?>