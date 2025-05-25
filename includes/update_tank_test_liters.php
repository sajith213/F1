<?php
/**
 * Test Liters Tank Update Integration
 * 
 * This file handles updating tank volumes when test liters are recorded in the cash settlement process.
 * It serves as a bridge between the Cash Settlement module and Tank Management module.
 * 
 * When fuel is dispensed for testing purposes, it needs to be added back to the tank's inventory
 * since it wasn't actually sold to a customer.
 */

// Include necessary files
require_once __DIR__ . '/../../includes/db.php';

/**
 * Update tank volume for test liters
 * 
 * @param int $pump_id The ID of the pump that dispensed the test liters
 * @param float $test_liters The volume of fuel used for testing
 * @param int $staff_id The ID of the staff who performed the test
 * @param string $record_date The date the test was performed
 * @param string $shift The shift during which the test occurred (optional)
 * @return bool True on success, false on failure
 */
function updateTankForTestLiters($pump_id, $test_liters, $staff_id, $record_date, $shift = null) {
    global $conn;
    
    // Input validation
    if ($pump_id <= 0 || $test_liters <= 0 || $staff_id <= 0) {
        error_log("Invalid parameters for updateTankForTestLiters: pump_id=$pump_id, test_liters=$test_liters, staff_id=$staff_id");
        return false;
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Step 1: Find which tank is connected to this pump
        $query = "SELECT tank_id FROM pumps WHERE pump_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pump_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("No tank found for pump ID: $pump_id");
        }
        
        $tank_id = $result->fetch_assoc()['tank_id'];
        $stmt->close();
        
        // Step 2: Get current tank volume
        $query = "SELECT current_volume FROM tanks WHERE tank_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $tank_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Tank not found with ID: $tank_id");
        }
        
        $current_volume = $result->fetch_assoc()['current_volume'];
        $stmt->close();
        
        // Step 3: Update tank volume by adding the test liters back
        $new_volume = $current_volume + $test_liters;
        $update_query = "UPDATE tanks SET current_volume = ?, updated_at = NOW() WHERE tank_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("di", $new_volume, $tank_id);
        $stmt->execute();
        $stmt->close();
        
        // Step 4: Record this operation in tank_inventory table
        $operation_type = 'adjustment';
        $staff_name_query = "SELECT CONCAT(first_name, ' ', last_name) as staff_name FROM staff WHERE staff_id = ?";
        $stmt = $conn->prepare($staff_name_query);
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $staff_name = $stmt->get_result()->fetch_assoc()['staff_name'] ?? 'Unknown Staff';
        $stmt->close();
        
        $shift_info = $shift ? ", $shift shift" : "";
        $notes = "Test liters added back to tank. Dispensed by $staff_name on " . date('Y-m-d', strtotime($record_date)) . "$shift_info";
        
        $recorded_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to admin if no session
        $insert_query = "INSERT INTO tank_inventory 
                        (tank_id, operation_type, previous_volume, change_amount, new_volume, operation_date, notes, recorded_by) 
                        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isddsis", $tank_id, $operation_type, $current_volume, $test_liters, $new_volume, $notes, $recorded_by);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error updating tank for test liters: " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk process test liters updates from multiple pump-shift combinations
 * 
 * @param array $test_liters_data Array of test liter data with pump_id, test_liters, staff_id, etc.
 * @return array Array with success count and error count
 */
function bulkUpdateTanksForTestLiters($test_liters_data) {
    $success_count = 0;
    $error_count = 0;
    
    foreach ($test_liters_data as $data) {
        if (!isset($data['pump_id']) || !isset($data['test_liters']) || 
            !isset($data['staff_id']) || !isset($data['record_date'])) {
            $error_count++;
            continue;
        }
        
        // Skip if test liters is zero or negative
        if (floatval($data['test_liters']) <= 0) {
            continue;
        }
        
        $result = updateTankForTestLiters(
            $data['pump_id'],
            $data['test_liters'],
            $data['staff_id'],
            $data['record_date'],
            $data['shift'] ?? null
        );
        
        if ($result) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    return [
        'success_count' => $success_count,
        'error_count' => $error_count
    ];
}
?>