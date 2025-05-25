<?php
/**
 * Get Meter Readings AJAX Handler
 * 
 * This file handles AJAX requests to retrieve meter readings for a specific pump and date
 */

// Start session if not already started
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Include required files
require_once '../../includes/db.php';
require_once 'functions.php';

// Set content type
header('Content-Type: application/json');

// Check if required parameters are provided
if (!isset($_GET['pump_id']) || !isset($_GET['date'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Get parameters
$pump_id = intval($_GET['pump_id']);
$date = $_GET['date'];
$shift = isset($_GET['shift']) ? $_GET['shift'] : null;

// Validate pump_id
if ($pump_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid pump ID'
    ]);
    exit;
}

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format'
    ]);
    exit;
}

try {
    // Get meter readings for the pump
    $readings_data = getPumpMeterReadings($pump_id, $date, $shift);
    
    // FIXED: Rename key if it exists but with wrong name
    if (isset($readings_data['total_expected'])) {
        $readings_data['total_expected_amount'] = $readings_data['total_expected'];
        unset($readings_data['total_expected']);
    } else if (!isset($readings_data['total_expected_amount'])) {
        // If neither key exists, provide a default
        $readings_data['total_expected_amount'] = 0;
    }
    
    // FIXED: Add required primary_fuel_price field if missing
    if (!isset($readings_data['primary_fuel_price'])) {
        // Get the fuel price from the database for this pump
        $query = "SELECT ft.fuel_type_id, fp.selling_price 
                 FROM pumps p 
                 JOIN tanks t ON p.tank_id = t.tank_id 
                 JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id 
                 JOIN fuel_prices fp ON ft.fuel_type_id = fp.fuel_type_id 
                 WHERE p.pump_id = ? AND fp.status = 'active' 
                 ORDER BY fp.effective_date DESC LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $pump_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $readings_data['primary_fuel_price'] = (float)$row['selling_price'];
        } else {
            // Default value if no price is found
            $readings_data['primary_fuel_price'] = 0;
        }
    }
    
    // Add success flag
    $readings_data['success'] = true;
    
    // Add currency symbol if not already present
    if (!isset($readings_data['currency_symbol'])) {
        // Get currency symbol from settings
        $currency_symbol = 'Rs.'; // Default changed to match your system
        $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currency_symbol = $row['setting_value'];
        }
        $readings_data['currency_symbol'] = $currency_symbol;
    }
    
    // Return the readings data
    echo json_encode($readings_data);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error getting meter readings: ' . $e->getMessage()
    ]);
}
?>