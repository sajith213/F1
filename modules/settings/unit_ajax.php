<?php
/**
 * Unit AJAX Handler
 * 
 * This file handles AJAX requests for unit management
 */

// Start session
session_start();

// Include database connection
require_once '../../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Determine the action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_units':
        getUnits();
        break;
    
    case 'add_unit':
        addUnit();
        break;
    
    case 'update_unit':
        updateUnit();
        break;
    
    case 'delete_unit':
        deleteUnit();
        break;
    
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
        break;
}

/**
 * Get all units
 */
function getUnits() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT unit_id, unit_name, unit_symbol, status FROM units ORDER BY unit_name");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'units' => $units
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch units: ' . $conn->error
        ]);
    }
}

/**
 * Add a new unit
 */
function addUnit() {
    global $conn;
    
    // Get unit data
    $unit_name = trim($_POST['unit_name'] ?? '');
    $unit_symbol = trim($_POST['unit_symbol'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate input
    if (empty($unit_name)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unit name is required'
        ]);
        return;
    }
    
    if (empty($unit_symbol)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unit symbol is required'
        ]);
        return;
    }
    
    // Check if unit already exists
    $stmt = $conn->prepare("SELECT unit_id FROM units WHERE unit_name = ? OR unit_symbol = ?");
    
    if ($stmt) {
        $stmt->bind_param("ss", $unit_name, $unit_symbol);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Unit with this name or symbol already exists'
            ]);
            $stmt->close();
            return;
        }
        
        $stmt->close();
    }
    
    // Insert new unit
    $stmt = $conn->prepare("INSERT INTO units (unit_name, unit_symbol, status) VALUES (?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sss", $unit_name, $unit_symbol, $status);
        
        if ($stmt->execute()) {
            $unit_id = $conn->insert_id;
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Unit added successfully',
                'unit_id' => $unit_id,
                'unit_name' => $unit_name,
                'unit_symbol' => $unit_symbol
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to add unit: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
    }
}

/**
 * Update an existing unit
 */
function updateUnit() {
    global $conn;
    
    // Get unit data
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $unit_name = trim($_POST['unit_name'] ?? '');
    $unit_symbol = trim($_POST['unit_symbol'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate input
    if ($unit_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid unit ID'
        ]);
        return;
    }
    
    if (empty($unit_name)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unit name is required'
        ]);
        return;
    }
    
    if (empty($unit_symbol)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unit symbol is required'
        ]);
        return;
    }
    
    // Check if unit name already exists for another unit
    $stmt = $conn->prepare("SELECT unit_id FROM units WHERE (unit_name = ? OR unit_symbol = ?) AND unit_id != ?");
    
    if ($stmt) {
        $stmt->bind_param("ssi", $unit_name, $unit_symbol, $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Another unit with this name or symbol already exists'
            ]);
            $stmt->close();
            return;
        }
        
        $stmt->close();
    }
    
    // Update unit
    $stmt = $conn->prepare("UPDATE units SET unit_name = ?, unit_symbol = ?, status = ? WHERE unit_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("sssi", $unit_name, $unit_symbol, $status, $unit_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Unit updated successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update unit: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
    }
}

/**
 * Delete a unit
 */
function deleteUnit() {
    global $conn;
    
    // Get unit ID
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    
    // Validate input
    if ($unit_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid unit ID'
        ]);
        return;
    }
    
    // Check if unit is in use
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE unit_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This unit cannot be deleted because it is being used by products'
            ]);
            $stmt->close();
            return;
        }
        
        $stmt->close();
    }
    
    // Delete unit
    $stmt = $conn->prepare("DELETE FROM units WHERE unit_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $unit_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Unit deleted successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete unit: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
    }
}