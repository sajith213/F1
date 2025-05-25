<?php
/**
 * Tank Management Functions
 * 
 * Common functions used across the tank management module
 */

/**
 * Get tank information by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $tank_id Tank ID
 * @return array|null Tank data or null if not found
 */
function getTankById($conn, $tank_id) {
    $stmt = $conn->prepare("SELECT t.*, f.fuel_name 
                           FROM tanks t
                           JOIN fuel_types f ON t.fuel_type_id = f.fuel_type_id
                           WHERE t.tank_id = ?");
    $stmt->bind_param("i", $tank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get tank inventory operations by tank ID
 * 
 * @param mysqli $conn Database connection
 * @param int $tank_id Tank ID
 * @param int $limit Number of records to retrieve (0 for all)
 * @return array Array of tank inventory operations
 */
function getTankInventoryOperations($conn, $tank_id, $limit = 0) {
    $query = "SELECT ti.*, u.full_name as recorded_by_name
             FROM tank_inventory ti
             JOIN users u ON ti.recorded_by = u.user_id
             WHERE ti.tank_id = ?
             ORDER BY ti.operation_date DESC";
    
    if ($limit > 0) {
        $query .= " LIMIT " . intval($limit);
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $operations = [];
    while ($row = $result->fetch_assoc()) {
        $operations[] = $row;
    }
    
    return $operations;
}

/**
 * Get connected pumps for a tank
 * 
 * @param mysqli $conn Database connection
 * @param int $tank_id Tank ID
 * @return array Array of pumps connected to the tank
 */
function getConnectedPumps($conn, $tank_id) {
    $stmt = $conn->prepare("SELECT p.* 
                           FROM pumps p
                           WHERE p.tank_id = ?");
    $stmt->bind_param("i", $tank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pumps = [];
    while ($row = $result->fetch_assoc()) {
        $pumps[] = $row;
    }
    
    return $pumps;
}

/**
 * Record a tank inventory operation
 * 
 * @param mysqli $conn Database connection
 * @param int $tank_id Tank ID
 * @param string $operation_type Type of operation (delivery, sales, adjustment, leak, transfer, initial, test)
 * @param float $previous_volume Previous volume
 * @param float $change_amount Amount to add (positive) or remove (negative)
 * @param string $notes Operation notes
 * @param int $reference_id Reference ID (optional, for linking to orders, deliveries, etc.)
 * @return bool True on success, false on failure
 */
function recordTankOperation($conn, $tank_id, $operation_type, $previous_volume, $change_amount, $notes, $reference_id = null) {
    // Calculate new volume
    $new_volume = $previous_volume + $change_amount;
    
    // Get current timestamp
    $operation_date = date('Y-m-d H:i:s');
    
    // Get the current user ID from session
    $recorded_by = $_SESSION['user_id'] ?? 0;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert tank inventory record
        $stmt = $conn->prepare("INSERT INTO tank_inventory 
                               (tank_id, operation_type, reference_id, previous_volume, change_amount, new_volume, operation_date, notes, recorded_by) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("isidddssi", $tank_id, $operation_type, $reference_id, $previous_volume, $change_amount, $new_volume, $operation_date, $notes, $recorded_by);
        $stmt->execute();
        
        // Update the tank's current volume
        $update_stmt = $conn->prepare("UPDATE tanks SET current_volume = ?, updated_at = NOW() WHERE tank_id = ?");
        $update_stmt->bind_param("di", $new_volume, $tank_id);
        $update_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error recording tank operation: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if tank has low fuel level
 * 
 * @param array $tank Tank data array
 * @return bool True if tank level is below threshold, false otherwise
 */
function hasTankLowLevel($tank) {
    if (!isset($tank['current_volume']) || !isset($tank['low_level_threshold'])) {
        return false;
    }
    
    return ($tank['current_volume'] <= $tank['low_level_threshold']);
}

/**
 * Calculate tank fill percentage
 * 
 * @param float $current_volume Current volume
 * @param float $capacity Tank capacity
 * @return float Fill percentage (0-100)
 */
function calculateFillPercentage($current_volume, $capacity) {
    if ($capacity <= 0) {
        return 0;
    }
    
    $percentage = ($current_volume / $capacity) * 100;
    return min(100, max(0, $percentage)); // Ensure between 0 and 100
}

/**
 * Get color class based on fill percentage
 * 
 * @param float $percentage Fill percentage
 * @return string CSS color class name
 */
function getFillColorClass($percentage) {
    if ($percentage < 20) {
        return 'red';
    } elseif ($percentage < 50) {
        return 'yellow';
    } else {
        return 'green';
    }
}

/**
 * Get status color class
 * 
 * @param string $status Tank status
 * @return string CSS color class name
 */
function getStatusColorClass($status) {
    switch ($status) {
        case 'active':
            return 'green';
        case 'maintenance':
            return 'yellow';
        case 'inactive':
            return 'red';
        default:
            return 'gray';
    }
}

/**
 * Format volume for display
 * 
 * @param float $volume Volume in liters
 * @param int $decimals Number of decimal places
 * @return string Formatted volume
 */
function formatVolume($volume, $decimals = 2) {
    return number_format($volume, $decimals) . ' L';
}

/**
 * Validate tank data
 * 
 * @param array $data Tank data to validate
 * @return array Array of error messages (empty if no errors)
 */
function validateTankData($data) {
    $errors = [];
    
    if (empty($data['tank_name'])) {
        $errors[] = "Tank name is required";
    }
    
    if (empty($data['fuel_type_id']) || intval($data['fuel_type_id']) <= 0) {
        $errors[] = "Please select a valid fuel type";
    }
    
    if (empty($data['capacity']) || floatval($data['capacity']) <= 0) {
        $errors[] = "Capacity must be greater than zero";
    }
    
    if (isset($data['current_volume']) && floatval($data['current_volume']) < 0) {
        $errors[] = "Current volume cannot be negative";
    }
    
    if (isset($data['current_volume']) && isset($data['capacity']) && 
        floatval($data['current_volume']) > floatval($data['capacity'])) {
        $errors[] = "Current volume cannot exceed tank capacity";
    }
    
    if (isset($data['low_level_threshold']) && isset($data['capacity']) && 
        (floatval($data['low_level_threshold']) < 0 || 
         floatval($data['low_level_threshold']) > floatval($data['capacity']))) {
        $errors[] = "Low level threshold must be between 0 and tank capacity";
    }
    
    return $errors;
}