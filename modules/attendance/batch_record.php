<?php
/**
 * Batch Attendance Recording
 * 
 * This file processes batch attendance records submitted from the record_attendance.php form
 */

// Include database connection
require_once('../../includes/db.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Include module functions
require_once('functions.php');

// Initialize variables
$success_count = 0;
$error_count = 0;
$errors = [];

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the batch date
    $batch_date = $_POST['batch_date'] ?? date('Y-m-d');
    
    // Get all staff IDs
    $staff_ids = $_POST['staff_ids'] ?? [];
    
    // If no staff selected, redirect back with error
    if (empty($staff_ids)) {
        header("Location: record_attendance.php?error=no_staff_selected");
        exit;
    }
    
    // Loop through each selected staff member
    foreach ($staff_ids as $staff_id) {
        // Get status, time in, and time out for this staff member
        $status = $_POST['batch_status'][$staff_id] ?? 'present';
        $time_in = !empty($_POST['batch_time_in'][$staff_id]) ? 
            date('Y-m-d H:i:s', strtotime("$batch_date {$_POST['batch_time_in'][$staff_id]}")) : null;
        $time_out = !empty($_POST['batch_time_out'][$staff_id]) ? 
            date('Y-m-d H:i:s', strtotime("$batch_date {$_POST['batch_time_out'][$staff_id]}")) : null;
        
        // For absent or leave status, don't record time
        if ($status === 'absent' || $status === 'leave') {
            $time_in = null;
            $time_out = null;
        }
        
        // Record attendance for this staff member
        $result = recordAttendance(
            $staff_id,
            $batch_date,
            $status,
            $time_in,
            $time_out,
            null, // No remarks for batch processing
            $_SESSION['user_id']
        );
        
        if ($result === true) {
            $success_count++;
            
            // If time_in and time_out provided, check for overtime
            if ($time_in && $time_out) {
                $hours = calculateHours($time_in, $time_out);
                if ($hours > 8) {
                    // Get attendance record ID
                    $record = getAttendanceRecord($staff_id, $batch_date);
                    if ($record) {
                        $overtime_hours = $hours - 8;
                        recordOvertime($record['attendance_id'], $overtime_hours);
                    }
                }
            }
        } else {
            $error_count++;
            $errors[] = "Error for staff ID $staff_id: " . $result;
        }
    }
    
    // Redirect back with success message
    if ($error_count === 0) {
        header("Location: index.php?success=1&batch=1&count=$success_count");
    } else {
        $error_list = implode(', ', $errors);
        header("Location: record_attendance.php?batch_error=$error_list&success_count=$success_count&error_count=$error_count");
    }
    exit;
} else {
    // If accessed directly without POST data, redirect to attendance page
    header("Location: index.php");
    exit;
}