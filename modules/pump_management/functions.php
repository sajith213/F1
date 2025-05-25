<?php
// Add this at the top of functions.php (before any functions)

// 1. Set up custom error log
$custom_log = __DIR__ . '/meter_errors.log';
ini_set('error_log', $custom_log);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 2. Custom error handler to catch issues
function meter_error_handler($errno, $errstr, $errfile, $errline) {
    error_log("METER ERROR [$errno] $errstr in $errfile on line $errline");
    return false; // Continue with PHP's internal error handler
}
set_error_handler("meter_error_handler");
/**
 * Pump Management Functions
 * 
 * This file contains functions specific to the pump management module
 */

/**
 * Get pending meter readings that need verification
 * 
 * @return array Array of pending meter readings
 */
function getPendingMeterReadings() {
    global $conn;
    
    $query = "SELECT mr.*, 
                     pn.nozzle_number, 
                     p.pump_name,
                     ft.fuel_name,
                     u.full_name as recorded_by_name
              FROM meter_readings mr
              JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
              JOIN pumps p ON pn.pump_id = p.pump_id
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              LEFT JOIN users u ON mr.recorded_by = u.user_id
              WHERE mr.verification_status = 'pending'
              ORDER BY mr.reading_date DESC, p.pump_name, pn.nozzle_number";
    
    $result = $conn->query($query);
    
    $readings = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $readings[] = $row;
        }
    }
    
    return $readings;
}

/**
 * Verify a meter reading
 * 
 * @param int $reading_id Reading ID to verify
 * @param int $verified_by User ID who verified the reading
 * @return bool True if successful, false otherwise
 */
function verifyMeterReading($reading_id, $verified_by) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Get reading details
        $stmt = $conn->prepare("SELECT mr.*, pn.pump_id, pn.fuel_type_id, t.tank_id
                                FROM meter_readings mr
                                JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
                                JOIN pumps p ON pn.pump_id = p.pump_id
                                JOIN tanks t ON p.tank_id = t.tank_id
                                WHERE mr.reading_id = ?");
        $stmt->bind_param("i", $reading_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Reading not found");
        }
        
        $reading = $result->fetch_assoc();
        
        // Update the meter reading status
        $stmt = $conn->prepare("UPDATE meter_readings 
                                SET verification_status = 'verified', 
                                    verified_by = ?, 
                                    verification_date = NOW() 
                                WHERE reading_id = ?");
        $stmt->bind_param("ii", $verified_by, $reading_id);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to update reading status");
        }
        
        // Update tank inventory if not already processed
        $check_stmt = $conn->prepare("SELECT inventory_id 
                                     FROM tank_inventory 
                                     WHERE tank_id = ? AND reference_id = ? AND operation_type = 'sales'");
        $check_stmt->bind_param("ii", $reading['tank_id'], $reading_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Get current tank volume
            $tank_stmt = $conn->prepare("SELECT current_volume FROM tanks WHERE tank_id = ?");
            $tank_stmt->bind_param("i", $reading['tank_id']);
            $tank_stmt->execute();
            $tank_result = $tank_stmt->get_result();
            $tank = $tank_result->fetch_assoc();
            
            $previous_volume = $tank['current_volume'];
            $change_amount = -$reading['volume_dispensed']; // Negative as it's a reduction
            $new_volume = $previous_volume + $change_amount;
            
            // Add tank inventory record
            $inv_stmt = $conn->prepare("INSERT INTO tank_inventory 
                                       (tank_id, operation_type, reference_id, previous_volume, change_amount, new_volume, 
                                        operation_date, recorded_by) 
                                       VALUES (?, 'sales', ?, ?, ?, ?, NOW(), ?)");
            $inv_stmt->bind_param("iidddi", 
                $reading['tank_id'], 
                $reading_id, 
                $previous_volume, 
                $change_amount, 
                $new_volume, 
                $verified_by
            );
            $result = $inv_stmt->execute();
            
            if (!$result) {
                throw new Exception("Failed to update tank inventory");
            }
            
            // Update tank current volume
            $update_stmt = $conn->prepare("UPDATE tanks SET current_volume = ? WHERE tank_id = ?");
            $update_stmt->bind_param("di", $new_volume, $reading['tank_id']);
            $result = $update_stmt->execute();
            
            if (!$result) {
                throw new Exception("Failed to update tank volume");
            }
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error verifying meter reading: " . $e->getMessage());
        return false;
    }
}

/**
 * Dispute a meter reading
 * 
 * @param int $reading_id Reading ID to dispute
 * @param int $verified_by User ID who disputed the reading
 * @param string $notes Dispute notes/reason
 * @return bool True if successful, false otherwise
 */
function disputeMeterReading($reading_id, $verified_by, $notes) {
    global $conn;
    
    try {
        // Update the meter reading status
        $stmt = $conn->prepare("UPDATE meter_readings 
                                SET verification_status = 'disputed', 
                                    verified_by = ?, 
                                    verification_date = NOW(), 
                                    notes = ? 
                                WHERE reading_id = ?");
        $stmt->bind_param("isi", $verified_by, $notes, $reading_id);
        $result = $stmt->execute();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error disputing meter reading: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a new meter reading (Refined Version)
 *
 * @param array $data Reading data array expecting 'nozzle_id', 'reading_date', 'opening_reading', 'closing_reading', 'recorded_by', 'notes'
 * @return int|false Reading ID if successful, false on failure
 */
function addMeterReading($data) {
    global $conn;

    // Ensure float values directly using floatval() for clarity
    $nozzle_id       = isset($data['nozzle_id']) ? (int)$data['nozzle_id'] : 0;
    $reading_date    = isset($data['reading_date']) ? $data['reading_date'] : date('Y-m-d');
    $opening_reading = isset($data['opening_reading']) ? floatval($data['opening_reading']) : 0.0;
    $closing_reading = isset($data['closing_reading']) ? floatval($data['closing_reading']) : 0.0;
    $recorded_by     = isset($data['recorded_by']) ? (int)$data['recorded_by'] : 0;
    $notes           = isset($data['notes']) ? trim($data['notes']) : '';

    // Calculate volume dispensed directly as float
    $volume_dispensed = $closing_reading - $opening_reading;

    // --- Logging ---
    error_log("Add Reading Attempt:");
    error_log("Data In: " . print_r($data, true)); // Log raw input data
    error_log("Processed - O: $opening_reading, C: $closing_reading, V: $volume_dispensed");
    error_log("Data Types - O: " . gettype($opening_reading) . ", C: " . gettype($closing_reading) . ", V: " . gettype($volume_dispensed));
    error_log("PHP Precision: " . ini_get('precision')); // Check PHP's float precision setting

    // --- Database Interaction ---
    $query = "INSERT INTO meter_readings (nozzle_id, reading_date, opening_reading, closing_reading, volume_dispensed, recorded_by, notes)
              VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Failed to prepare statement: " . $conn->error);
        return false;
    }

    // Bind parameters using standard types ('d' for decimal/double)
    // Types: i=integer, s=string, d=double
    $result_bind = $stmt->bind_param("isdddis",  // Use 'd' for opening, closing, volume
        $nozzle_id,
        $reading_date,
        $opening_reading,      // Bind as double
        $closing_reading,      // Bind as double
        $volume_dispensed,     // Bind calculated volume as double
        $recorded_by,
        $notes
    );

    if ($result_bind === false) {
        error_log("Failed to bind parameters: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $result_execute = $stmt->execute();

    if (!$result_execute) {
        error_log("Failed to execute insert meter reading: " . $stmt->error . " | Volume Sent: " . $volume_dispensed); // Log volume on error
        $reading_id = false;
    } else {
        $reading_id = $conn->insert_id;
         // error_log("Successfully inserted meter reading ID: $reading_id"); // Optional: Uncomment for success log
    }

    $stmt->close();
    return $reading_id;
}
// ... (rest of the functions like updateMeterReading, getAllPumps, etc.) ...
/**
 * Update an existing meter reading with volume calculation
 * * @param array $reading_data Reading data to update
 * @return bool True if successful, false otherwise
 */
function updateMeterReading($data) {
    global $conn;

    // Ensure values are not null and cast to float
    $opening_reading = isset($data['opening_reading']) ? (float)$data['opening_reading'] : 0;
    $closing_reading = isset($data['closing_reading']) ? (float)$data['closing_reading'] : 0;

    // Calculate volume dispensed safely
    $volume_dispensed = $closing_reading - $opening_reading; // Use direct float calculation

    // --- Logging for Update ---
    error_log("Update Reading Attempt:");
    error_log("Data In: " . print_r($data, true)); 
    error_log("Processed - O: $opening_reading, C: $closing_reading, V: $volume_dispensed, ID: " . ($data['reading_id'] ?? 'N/A'));
    error_log("Data Types - O: " . gettype($opening_reading) . ", C: " . gettype($closing_reading) . ", V: " . gettype($volume_dispensed));

    $query = "UPDATE meter_readings SET 
              opening_reading = ?,
              closing_reading = ?,
              volume_dispensed = ?,
              recorded_by = ?,
              notes = ?
              WHERE reading_id = ?";

    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log("Update Failed - Prepare Error: " . $conn->error);
        return false;
    }

    // Bind using standard types: d=double, i=integer, s=string
    $result_bind = $stmt->bind_param("dddisi", 
        $opening_reading,  // Bind as double
        $closing_reading,  // Bind as double
        $volume_dispensed, // Bind calculated volume as double
        $data['recorded_by'], // Bind as integer
        $data['notes'],       // Bind as string
        $data['reading_id']   // Bind as integer
    );

    if ($result_bind === false) {
        error_log("Update Failed - Bind Error: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $result_execute = $stmt->execute();

    if (!$result_execute) {
         error_log("Update Failed - Execute Error: " . $stmt->error . " | Volume Sent: " . $volume_dispensed . " | ID: " . ($data['reading_id'] ?? 'N/A'));
    } else {
        // error_log("Successfully updated meter reading ID: " . ($data['reading_id'] ?? 'N/A')); // Optional success log
    }

    $stmt->close();

    return $result_execute; // Return the result of execute()
}
/**
 * Get all pumps with optional filters
 * 
 * @param array $filters Optional filters (status)
 * @return array Array of pumps
 */
function getAllPumps($filters = []) {
    global $conn;
    
    // If $filters is a mysqli object (for backward compatibility), ignore it and use empty filters
    if ($filters instanceof mysqli) {
        $filters = [];
    }
    
    $query = "SELECT p.*, t.tank_name, t.fuel_type_id, t.current_volume, t.capacity, ft.fuel_name
              FROM pumps p
              JOIN tanks t ON p.tank_id = t.tank_id
              JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id";
    
    $conditions = [];
    $params = [];
    $types = "";
    
    if (isset($filters['status']) && !empty($filters['status'])) {
        $conditions[] = "p.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY p.pump_name";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pumps = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pumps[] = $row;
        }
    }
    
    return $pumps;
}

/**
 * Get previous day's closing readings for all nozzles
 * 
 * @param string $current_date Current reading date in Y-m-d format
 * @return array Array of nozzle_id => closing_reading
 */
function getPreviousDayClosingReadings($current_date) {
    global $conn;
    
    // Calculate previous day
    $prev_date = date('Y-m-d', strtotime($current_date . ' -1 day'));
    
    $query = "SELECT nozzle_id, closing_reading 
              FROM meter_readings 
              WHERE reading_date = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $prev_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $readings = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $readings[$row['nozzle_id']] = $row['closing_reading'];
        }
    }
    
    return $readings;
}

/**
 * Get all pump nozzles with related information
 * Modified to include pump and nozzle status for both active and inactive pumps
 * 
 * @param int|null $pump_id Optional pump ID to filter by
 * @return array Array of nozzles
 */
/**
 * Get all active pump nozzles with related information
 * Modified to exclude inactive pumps but include pumps without staff assignments
 * 
 * @param int|null $pump_id Optional pump ID to filter by
 * @return array Array of nozzles
 */
function getPumpNozzles($pump_id = null) {
    global $conn;
    
    // Handle case when connection object is passed instead of pump_id
    if ($pump_id instanceof mysqli) {
        $pump_id = null;
    }
    
    $query = "SELECT pn.nozzle_id, pn.nozzle_number, pn.status as nozzle_status, 
                     p.pump_id, p.pump_name, p.status as pump_status, 
                     ft.fuel_type_id, ft.fuel_name
              FROM pump_nozzles pn
              JOIN pumps p ON pn.pump_id = p.pump_id
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              WHERE p.status != 'inactive' AND pn.status = 'active'";
    
    if ($pump_id !== null) {
        $query .= " AND pn.pump_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pump_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query .= " ORDER BY p.pump_name, pn.nozzle_number";
        $result = $conn->query($query);
    }
    
    $nozzles = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $nozzles[] = $row;
        }
    }
    
    return $nozzles;
}

/**
 * Get recent meter readings
 * 
 * @param int $limit Number of readings to return
 * @param int $nozzle_id Filter by nozzle ID (optional)
 * @return array Array of meter readings
 */
function getRecentMeterReadings($limit = 10, $nozzle_id = null) {
    global $conn;
    
    // Handle case when connection object is passed instead of limit
    if ($limit instanceof mysqli) {
        $limit = 10;
    }
    
    // Handle case when connection object is passed as nozzle_id
    if ($nozzle_id instanceof mysqli) {
        $nozzle_id = null;
    }
    
    $query = "SELECT mr.*, pn.nozzle_number, p.pump_name, p.pump_id, ft.fuel_name,
                     u.full_name as recorded_by_name,
                     v.full_name as verified_by_name
              FROM meter_readings mr
              JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
              JOIN pumps p ON pn.pump_id = p.pump_id
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              LEFT JOIN users u ON mr.recorded_by = u.user_id
              LEFT JOIN users v ON mr.verified_by = v.user_id";
    
    $conditions = [];
    $params = [];
    $types = "";
    
    if ($nozzle_id !== null) {
        $conditions[] = "mr.nozzle_id = ?";
        $params[] = $nozzle_id;
        $types .= "i";
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY mr.reading_date DESC, mr.reading_id DESC";
    
    if ($limit > 0) {
        $query .= " LIMIT ?";
        $params[] = $limit;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $readings = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $readings[] = $row;
        }
    }
    
    return $readings;
}

/**
 * Add a new pump
 * 
 * @param array $pump_data Pump data
 * @return int|false Pump ID if successful, false on failure
 */
function addPump($pump_data) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Insert pump record
        $stmt = $conn->prepare("INSERT INTO pumps 
                               (pump_name, tank_id, status, model, installation_date, notes) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sissss",
            $pump_data['pump_name'],
            $pump_data['tank_id'],
            $pump_data['status'],
            $pump_data['model'],
            $pump_data['installation_date'],
            $pump_data['notes']
        );
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to add pump");
        }
        
        $pump_id = $conn->insert_id;
        
        // Add nozzles if provided
        if (isset($pump_data['nozzles']) && is_array($pump_data['nozzles'])) {
            foreach ($pump_data['nozzles'] as $nozzle) {
                $nozzle_stmt = $conn->prepare("INSERT INTO pump_nozzles 
                                              (pump_id, nozzle_number, fuel_type_id, status) 
                                              VALUES (?, ?, ?, ?)");
                $nozzle_stmt->bind_param(
                    "iiis",
                    $pump_id,
                    $nozzle['nozzle_number'],
                    $nozzle['fuel_type_id'],
                    $nozzle['status'] ?? 'active'
                );
                $result = $nozzle_stmt->execute();
                
                if (!$result) {
                    throw new Exception("Failed to add nozzle");
                }
            }
        }
        
        $conn->commit();
        return $pump_id;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error adding pump: " . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing pump
 * 
 * @param int $pump_id Pump ID
 * @param array $pump_data Pump data
 * @return bool True if successful, false on failure
 */
function updatePump($pump_id, $pump_data) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Update pump record
        $stmt = $conn->prepare("UPDATE pumps 
                               SET pump_name = ?, tank_id = ?, status = ?, model = ?, 
                                   installation_date = ?, last_maintenance_date = ?, notes = ? 
                               WHERE pump_id = ?");
        $stmt->bind_param(
            "sisssssi",
            $pump_data['pump_name'],
            $pump_data['tank_id'],
            $pump_data['status'],
            $pump_data['model'],
            $pump_data['installation_date'],
            $pump_data['last_maintenance_date'],
            $pump_data['notes'],
            $pump_id
        );
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to update pump");
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error updating pump: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a pump
 * 
 * @param int $pump_id Pump ID
 * @return bool True if successful, false on failure
 */
function deletePump($pump_id) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Check if pump has any meter readings
        $check_stmt = $conn->prepare("
            SELECT mr.reading_id 
            FROM meter_readings mr
            JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
            WHERE pn.pump_id = ?
            LIMIT 1
        ");
        $check_stmt->bind_param("i", $pump_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception("Cannot delete pump with associated meter readings");
        }
        
        // Delete nozzles
        $nozzle_stmt = $conn->prepare("DELETE FROM pump_nozzles WHERE pump_id = ?");
        $nozzle_stmt->bind_param("i", $pump_id);
        $result = $nozzle_stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to delete pump nozzles");
        }
        
        // Delete pump
        $pump_stmt = $conn->prepare("DELETE FROM pumps WHERE pump_id = ?");
        $pump_stmt->bind_param("i", $pump_id);
        $result = $pump_stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to delete pump");
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error deleting pump: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a specific pump by ID
 * 
 * @param int $pump_id Pump ID
 * @return array|null Pump data or null if not found
 */
function getPumpById($pump_id) {
    global $conn;
    
    $query = "SELECT p.*, t.tank_name, t.fuel_type_id, t.current_volume, t.capacity, ft.fuel_name
              FROM pumps p
              JOIN tanks t ON p.tank_id = t.tank_id
              JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
              WHERE p.pump_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pump_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $pump = $result->fetch_assoc();
        
        // Get nozzles
        $pump['nozzles'] = getNozzlesByPumpId($pump_id);
        
        return $pump;
    }
    
    return null;
}

/**
 * Get all tanks
 * 
 * @return array Array of tanks
 */
function getTanks() {
    global $conn;
    
    $query = "SELECT t.*, ft.fuel_name
              FROM tanks t
              JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
              ORDER BY t.tank_name";
    
    $result = $conn->query($query);
    
    $tanks = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tanks[] = $row;
        }
    }
    
    return $tanks;
}

/**
 * Get all fuel types
 * 
 * @return array Array of fuel types
 */
function getFuelTypes() {
    global $conn;
    
    $query = "SELECT * FROM fuel_types ORDER BY fuel_name";
    
    $result = $conn->query($query);
    
    $fuel_types = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $fuel_types[] = $row;
        }
    }
    
    return $fuel_types;
}

/**
 * Get nozzles for a specific pump
 * 
 * @param int $pump_id Pump ID
 * @return array Array of nozzles
 */
function getNozzlesByPumpId($pump_id) {
    global $conn;
    
    $query = "SELECT pn.*, ft.fuel_name
              FROM pump_nozzles pn
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              WHERE pn.pump_id = ?
              ORDER BY pn.nozzle_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pump_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $nozzles = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $nozzles[] = $row;
        }
    }
    
    return $nozzles;
}

/**
 * Add a new nozzle
 * 
 * @param array $nozzle_data Nozzle data
 * @return int|false Nozzle ID if successful, false on failure
 */
function addNozzle($nozzle_data) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO pump_nozzles 
                               (pump_id, nozzle_number, fuel_type_id, status) 
                               VALUES (?, ?, ?, ?)");
        $stmt->bind_param(
            "iiis",
            $nozzle_data['pump_id'],
            $nozzle_data['nozzle_number'],
            $nozzle_data['fuel_type_id'],
            $nozzle_data['status']
        );
        $result = $stmt->execute();
        
        if ($result) {
            return $conn->insert_id;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log("Error adding nozzle: " . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing nozzle
 * 
 * @param int $nozzle_id Nozzle ID
 * @param array $nozzle_data Nozzle data
 * @return bool True if successful, false on failure
 */
function updateNozzle($nozzle_id, $nozzle_data) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE pump_nozzles 
                               SET nozzle_number = ?, fuel_type_id = ?, status = ? 
                               WHERE nozzle_id = ? AND pump_id = ?");
        $stmt->bind_param(
            "iisii",
            $nozzle_data['nozzle_number'],
            $nozzle_data['fuel_type_id'],
            $nozzle_data['status'],
            $nozzle_id,
            $nozzle_data['pump_id']
        );
        $result = $stmt->execute();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating nozzle: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a nozzle
 * 
 * @param int $nozzle_id Nozzle ID
 * @return bool True if successful, false on failure
 */
function deleteNozzle($nozzle_id) {
    global $conn;
    
    try {
        // Check if nozzle has meter readings
        $check_stmt = $conn->prepare("SELECT reading_id FROM meter_readings WHERE nozzle_id = ? LIMIT 1");
        $check_stmt->bind_param("i", $nozzle_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Cannot delete nozzle with readings
            return false;
        }
        
        $stmt = $conn->prepare("DELETE FROM pump_nozzles WHERE nozzle_id = ?");
        $stmt->bind_param("i", $nozzle_id);
        $result = $stmt->execute();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error deleting nozzle: " . $e->getMessage());
        return false;
    }
}

/**
 * Get meter readings by date
 * 
 * @param string $reading_date Reading date in Y-m-d format
 * @return array Array of meter readings
 */
function getMeterReadingsByDate($date) {
    global $conn;
    
    // Use explicit casting in the query to ensure decimal precision
    $query = "SELECT reading_id, nozzle_id, reading_date, 
              CAST(opening_reading AS DECIMAL(15,4)) as opening_reading,
              CAST(closing_reading AS DECIMAL(15,4)) as closing_reading,
              CAST(volume_dispensed AS DECIMAL(15,4)) as volume_dispensed,
              recorded_by, verification_status, verified_by, verification_date, notes 
              FROM meter_readings WHERE reading_date = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $readings = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure we're not losing precision during PHP processing
        if (isset($row['volume_dispensed'])) {
            $row['volume_dispensed'] = (float)$row['volume_dispensed'];
        }
        $readings[] = $row;
    }
    
    $stmt->close();
    return $readings;
}
/**
 * Count pumps by status
 * 
 * @param string $status Status to count (active, inactive, maintenance)
 * @return int Number of pumps with the given status
 */
function countPumpsByStatus($status) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM pumps WHERE status = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row['count'];
    }
    
    return 0;
}
?>