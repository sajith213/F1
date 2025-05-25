<?php
/**
 * Overtime Approval
 * 
 * This file processes overtime approval/rejection requests
 */

// Include database connection
require_once('../../includes/db.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: ../../login.php");
    exit;
}

// Include module functions
require_once('functions.php');

// Initialize variables
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Process the approval/rejection request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['overtime_id']) && isset($_POST['action'])) {
    $overtime_id = $_POST['overtime_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? null;
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $result = updateOvertimeStatus($overtime_id, $status, $_SESSION['user_id'], $notes);
        
        if ($result === true) {
            $response = [
                'success' => true,
                'message' => 'Overtime ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully',
                'status' => $status
            ];
        } else {
            $response = [
                'success' => false,
                'message' => $result
            ];
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;