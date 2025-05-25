<?php
// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$record_id = $data['record_id'] ?? 0;
$meter_expected = $data['meter_expected'] ?? 0;
$fuel_price = $data['fuel_price'] ?? 0;

// Input validation
if (!$record_id || $record_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid record ID']);
    exit;
}

// Database connection
require_once '../../includes/db.php';

// Start transaction for data consistency
$conn->begin_transaction();

try {
    // Update the record with verified status and meter expected amount
    $query = "UPDATE daily_cash_records SET 
              status = 'verified', 
              verification_date = NOW(),
              verified_by = ?,
              expected_amount = ?,
              settlement_status = 'processed' 
              WHERE record_id = ?";
              
    $stmt = $conn->prepare($query);
    $user_id = $_SESSION['user_id'];
    $stmt->bind_param("idi", $user_id, $meter_expected, $record_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update record: " . $stmt->error);
    }
    
    // Update the test info in cash_record_details
    $detail_query = "UPDATE cash_record_details SET 
                    meter_expected_amount = ?,
                    fuel_price_at_time = ?
                    WHERE record_id = ?";
                    
    $detail_stmt = $conn->prepare($detail_query);
    $detail_stmt->bind_param("ddi", $meter_expected, $fuel_price, $record_id);
    
    if (!$detail_stmt->execute()) {
        throw new Exception("Failed to update record details: " . $detail_stmt->error);
    }
    
    // Add a flag to prevent this record from being reset
    $flag_query = "INSERT INTO settlement_flags (record_id, flag_type, created_at) 
                  VALUES (?, 'verified_final', NOW()) 
                  ON DUPLICATE KEY UPDATE created_at = NOW()";
                  
    $flag_stmt = $conn->prepare($flag_query);
    $flag_stmt->bind_param("i", $record_id);
    $flag_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>