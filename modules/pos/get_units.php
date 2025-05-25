<?php
/**
 * Get Units Data
 * 
 * Returns JSON data of all units
 */

// Require database connection
require_once '../../includes/db.php';

// Check for session
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Get all units
$units = [];

$stmt = $conn->prepare("
    SELECT u.unit_id, u.unit_name, u.unit_symbol, u.unit_type, 
           u.is_base_unit, u.base_unit_id, u.conversion_factor, 
           b.unit_name as base_unit_name, b.unit_symbol as base_unit_symbol
    FROM units u
    LEFT JOIN units b ON u.base_unit_id = b.unit_id
    WHERE u.status = 'active'
    ORDER BY u.unit_type, u.unit_name
");

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}

$stmt->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($units);
?>