<?php
/**
 * Delete Pump
 * 
 * This script handles the deletion of a pump from the system
 */
ob_start();

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check if user is authorized to access this module
if (!hasPermission('pump_management', $_SESSION['role'])) {
    $_SESSION['error_message'] = "Access Denied: You do not have permission to delete pumps.";
    header("Location: index.php");
    exit;
}

// Check if pump ID is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pump_id'])) {
    $pump_id = (int)$_POST['pump_id'];
    
    // Get pump details before deletion (for confirmation message)
    $pump = getPumpById($pump_id);
    
    if ($pump) {
        // Check if the pump has any related meter readings
        $query = "SELECT mr.reading_id 
                  FROM meter_readings mr
                  JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
                  WHERE pn.pump_id = ?
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pump_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Pump has meter readings, cannot delete
            $_SESSION['error_message'] = "Cannot delete pump '{$pump['pump_name']}' because it has associated meter readings. Consider marking it as inactive instead.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // First delete the nozzles associated with this pump
                $delete_nozzles = $conn->prepare("DELETE FROM pump_nozzles WHERE pump_id = ?");
                $delete_nozzles->bind_param("i", $pump_id);
                $delete_nozzles->execute();
                
                // Now delete the pump
                $delete_pump = $conn->prepare("DELETE FROM pumps WHERE pump_id = ?");
                $delete_pump->bind_param("i", $pump_id);
                $delete_pump->execute();
                
                // Check if deletion was successful
                if ($delete_pump->affected_rows > 0) {
                    $conn->commit();
                    $_SESSION['success_message'] = "Pump '{$pump['pump_name']}' has been successfully deleted.";
                } else {
                    throw new Exception("Failed to delete pump.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_message'] = "Pump not found.";
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Handle GET requests for backward compatibility
    $pump_id = (int)$_GET['id'];
    
    // Get pump details before deletion (for confirmation message)
    $pump = getPumpById($pump_id);
    
    if ($pump) {
        // Check if the pump has any related meter readings
        $query = "SELECT mr.reading_id 
                  FROM meter_readings mr
                  JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
                  WHERE pn.pump_id = ?
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pump_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Pump has meter readings, cannot delete
            $_SESSION['error_message'] = "Cannot delete pump '{$pump['pump_name']}' because it has associated meter readings. Consider marking it as inactive instead.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // First delete the nozzles associated with this pump
                $delete_nozzles = $conn->prepare("DELETE FROM pump_nozzles WHERE pump_id = ?");
                $delete_nozzles->bind_param("i", $pump_id);
                $delete_nozzles->execute();
                
                // Now delete the pump
                $delete_pump = $conn->prepare("DELETE FROM pumps WHERE pump_id = ?");
                $delete_pump->bind_param("i", $pump_id);
                $delete_pump->execute();
                
                // Check if deletion was successful
                if ($delete_pump->affected_rows > 0) {
                    $conn->commit();
                    $_SESSION['success_message'] = "Pump '{$pump['pump_name']}' has been successfully deleted.";
                } else {
                    throw new Exception("Failed to delete pump.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_message'] = "Pump not found.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request. Pump ID is required.";
}

// Redirect back to the pump management page
header("Location: index.php");
exit;

ob_end_flush();
?>