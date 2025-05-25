<?php
/**
 * Process Test Liters
 * 
 * This file handles the return of test liters to the appropriate fuel tank
 * after they have been recorded in the cash settlement process.
 * 
 * Test liters are fuel that was dispensed for testing/calibration but not sold,
 * so they need to be added back to the fuel tank inventory.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../../includes/db.php';
require_once '../tank_management/functions.php';

/**
 * Process test liters and add them back to the tank
 * 
 * @param int $pump_id The pump ID
 * @param float $test_liters The amount of test liters
 * @param int $staff_id The staff ID who performed the test
 * @param string $record_date The date of the record
 * @param string $shift The shift during which the test was performed
 * @return array Result with status and message
 */
function processTestLiters($pump_id, $test_liters, $staff_id, $record_date, $shift = null) {
    global $conn;
    
    // Initialize result array
    $result = [
        'success' => false,
        'message' => '',
        'tank_id' => null
    ];
    
    // Validate input
    if (!is_numeric($pump_id) || $pump_id <= 0) {
        $result['message'] = "Invalid pump ID";
        return $result;
    }
    
    if (!is_numeric($test_liters) || $test_liters <= 0) {
        $result['message'] = "Invalid test liters amount";
        return $result;
    }
    
    // Find the tank associated with the pump
    $query = "SELECT p.tank_id, t.current_volume, t.capacity, t.tank_name, f.fuel_name 
              FROM pumps p
              JOIN tanks t ON p.tank_id = t.tank_id
              JOIN fuel_types f ON t.fuel_type_id = f.fuel_type_id
              WHERE p.pump_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pump_id);
    $stmt->execute();
    $tank_result = $stmt->get_result();
    
    if ($tank_result->num_rows === 0) {
        $result['message'] = "No tank found for pump ID $pump_id";
        return $result;
    }
    
    $tank = $tank_result->fetch_assoc();
    $tank_id = $tank['tank_id'];
    $current_volume = $tank['current_volume'];
    $capacity = $tank['capacity'];
    $tank_name = $tank['tank_name'];
    $fuel_name = $tank['fuel_name'];
    
    // Check if adding test liters would exceed tank capacity
    if (($current_volume + $test_liters) > $capacity) {
        $result['message'] = "Adding test liters would exceed tank capacity";
        return $result;
    }
    
    // Format shift for the notes
    $shift_text = $shift ? " during " . ucfirst($shift) . " shift" : "";
    
    // Prepare notes for the operation
    $notes = "Test liters return from pump #$pump_id$shift_text on " . date('Y-m-d', strtotime($record_date)) . 
             ". Staff ID: $staff_id. Amount: " . number_format($test_liters, 2) . " L";
    
    // Use recordTankOperation from functions.php to add test liters back to tank
    $operation_type = 'adjustment'; // Using 'adjustment' as operation type for test liters
    $record_success = recordTankOperation($conn, $tank_id, $operation_type, $current_volume, $test_liters, $notes);
    
    if ($record_success) {
        $result['success'] = true;
        $result['message'] = "Successfully returned " . number_format($test_liters, 2) . " L of test liters to " . 
                             htmlspecialchars($tank_name) . " (" . htmlspecialchars($fuel_name) . ")";
        $result['tank_id'] = $tank_id;
    } else {
        $result['message'] = "Failed to record tank operation";
    }
    
    return $result;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access'
        ]);
        exit;
    }
    
    // Get JSON data
    $input_data = json_decode(file_get_contents('php://input'), true);
    
    // Process each test liter entry
    $results = [];
    
    if (isset($input_data['entries']) && is_array($input_data['entries'])) {
        foreach ($input_data['entries'] as $entry) {
            if (isset($entry['pump_id'], $entry['test_liters'], $entry['staff_id'], $entry['record_date'])) {
                $pump_id = intval($entry['pump_id']);
                $test_liters = floatval($entry['test_liters']);
                $staff_id = intval($entry['staff_id']);
                $record_date = $entry['record_date'];
                $shift = isset($entry['shift']) ? $entry['shift'] : null;
                
                if ($test_liters > 0) {
                    $result = processTestLiters($pump_id, $test_liters, $staff_id, $record_date, $shift);
                    $results[] = $result;
                }
            }
        }
    } else if (isset($_POST['pump_id'], $_POST['test_liters'], $_POST['staff_id'], $_POST['record_date'])) {
        // Handle regular form submission
        $pump_id = intval($_POST['pump_id']);
        $test_liters = floatval($_POST['test_liters']);
        $staff_id = intval($_POST['staff_id']);
        $record_date = $_POST['record_date'];
        $shift = isset($_POST['shift']) ? $_POST['shift'] : null;
        
        if ($test_liters > 0) {
            $result = processTestLiters($pump_id, $test_liters, $staff_id, $record_date, $shift);
            $results[] = $result;
        }
    } else {
        $results = [
            'success' => false,
            'message' => 'Missing required parameters'
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

/**
 * Process test liters in bulk for a cash settlement record
 * 
 * @param int $record_id The cash settlement record ID
 * @return array Results of the processing
 */
function processTestLitersForRecord($record_id) {
    global $conn;
    
    $results = [];
    
    // Get test liters data from the cash record
    $query = "SELECT dcr.staff_id, dcr.pump_id, dcr.shift, dcr.record_date, 
                     dcr.test_liters, p.tank_id 
              FROM daily_cash_records dcr
              JOIN pumps p ON dcr.pump_id = p.pump_id
              WHERE dcr.record_id = ? AND dcr.test_liters > 0";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $record_result = $stmt->get_result();
    
    if ($record_result->num_rows === 0) {
        return [
            'success' => false,
            'message' => 'No test liters found for record ID ' . $record_id
        ];
    }
    
    while ($record = $record_result->fetch_assoc()) {
        $test_liters = floatval($record['test_liters']);
        
        if ($test_liters > 0) {
            $result = processTestLiters(
                $record['pump_id'],
                $test_liters,
                $record['staff_id'],
                $record['record_date'],
                $record['shift']
            );
            
            $results[] = $result;
        }
    }
    
    return $results;
}

// If this file is included in another script, the following can be used:
// $results = processTestLitersForRecord($record_id);
?>