<?php
/**
 * Attendance Module Functions
 * 
 * Contains functions for attendance management
 */

// Include database connection
require_once __DIR__ . '/../../includes/db.php';

/**
 * Get all staff members for attendance
 * 
 * @param string $status Optional staff status filter
 * @return array Array of staff members
 */
function getStaffForAttendance($status = 'active') {
    global $conn;
    
    $query = "SELECT s.staff_id, s.staff_code, s.first_name, s.last_name, s.position, s.status, 
                     (SELECT MAX(a.attendance_date) FROM attendance_records a WHERE a.staff_id = s.staff_id) as last_attendance
              FROM staff s 
              WHERE s.status = ?
              ORDER BY s.first_name, s.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    $stmt->close();
    return $staff;
}

/**
 * Get attendance record for specific date and staff
 * 
 * @param int $staff_id Staff ID
 * @param string $date Date in Y-m-d format
 * @return array|null Attendance record or null if not found
 */
function getAttendanceRecord($staff_id, $date) {
    global $conn;
    
    $query = "SELECT ar.*, s.first_name, s.last_name, s.staff_code, u.full_name as recorded_by_name
              FROM attendance_records ar
              JOIN staff s ON ar.staff_id = s.staff_id
              LEFT JOIN users u ON ar.recorded_by = u.user_id
              WHERE ar.staff_id = ? AND ar.attendance_date = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $staff_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $record = $result->fetch_assoc();
    $stmt->close();
    
    return $record;
}

/**
 * Get attendance records for a date range
 * 
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @param int $staff_id Optional staff ID filter
 * @param string $status Optional attendance status filter
 * @return array Array of attendance records
 */
function getAttendanceRecords($start_date, $end_date, $staff_id = null, $status = null) {
    global $conn;
    
    $params = [];
    $types = "";
    
    $query = "SELECT ar.*, s.first_name, s.last_name, s.staff_code, s.position,
                     u.full_name as recorded_by_name
              FROM attendance_records ar
              JOIN staff s ON ar.staff_id = s.staff_id
              LEFT JOIN users u ON ar.recorded_by = u.user_id
              WHERE ar.attendance_date BETWEEN ? AND ?";
    
    $types .= "ss";
    $params[] = $start_date;
    $params[] = $end_date;
    
    if ($staff_id) {
        $query .= " AND ar.staff_id = ?";
        $types .= "i";
        $params[] = $staff_id;
    }
    
    if ($status) {
        $query .= " AND ar.status = ?";
        $types .= "s";
        $params[] = $status;
    }
    
    $query .= " ORDER BY ar.attendance_date DESC, s.first_name, s.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    $stmt->close();
    return $records;
}

/**
 * Record attendance for a staff member
 * 
 * @param int $staff_id Staff ID
 * @param string $date Attendance date in Y-m-d format
 * @param string $status Attendance status
 * @param string $time_in Time in (optional)
 * @param string $time_out Time out (optional)
 * @param string $remarks Additional remarks
 * @param int $recorded_by User ID who recorded this attendance
 * @return bool|string True on success, error message on failure
 */
function recordAttendance($staff_id, $date, $status, $time_in = null, $time_out = null, $remarks = null, $recorded_by = null) {
    global $conn;
    
    // Check if record already exists
    $existing = getAttendanceRecord($staff_id, $date);
    
    if ($existing) {
        // Update existing record
        $query = "UPDATE attendance_records 
                  SET status = ?, time_in = ?, time_out = ?, remarks = ?, recorded_by = ?, updated_at = NOW()
                  WHERE attendance_id = ?";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssii", $status, $time_in, $time_out, $remarks, $recorded_by, $existing['attendance_id']);
        
    } else {
        // Insert new record
        $query = "INSERT INTO attendance_records (staff_id, attendance_date, time_in, time_out, status, remarks, recorded_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssssi", $staff_id, $date, $time_in, $time_out, $status, $remarks, $recorded_by);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        return "Error: " . $stmt->error;
    }
    
    $stmt->close();
    return true;
}

/**
 * Get overtime records 
 * 
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @param int $staff_id Optional staff ID filter
 * @param string $status Optional approval status filter
 * @return array Array of overtime records
 */
function getOvertimeRecords($start_date, $end_date, $staff_id = null, $status = null) {
    global $conn;
    
    $params = [];
    $types = "";
    
    $query = "SELECT ot.*, ar.attendance_date, ar.time_in, ar.time_out, ar.hours_worked,
                     s.first_name, s.last_name, s.staff_code, s.position,
                     u.full_name as approved_by_name
              FROM overtime_records ot
              JOIN attendance_records ar ON ot.attendance_id = ar.attendance_id
              JOIN staff s ON ar.staff_id = s.staff_id
              LEFT JOIN users u ON ot.approved_by = u.user_id
              WHERE ar.attendance_date BETWEEN ? AND ?";
    
    $types .= "ss";
    $params[] = $start_date;
    $params[] = $end_date;
    
    if ($staff_id) {
        $query .= " AND ar.staff_id = ?";
        $types .= "i";
        $params[] = $staff_id;
    }
    
    if ($status) {
        $query .= " AND ot.status = ?";
        $types .= "s";
        $params[] = $status;
    }
    
    $query .= " ORDER BY ar.attendance_date DESC, s.first_name, s.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    $stmt->close();
    return $records;
}

/**
 * Record overtime for an attendance record
 * 
 * @param int $attendance_id Attendance ID
 * @param float $overtime_hours Number of overtime hours
 * @param float $overtime_rate Rate multiplier for overtime
 * @param string $notes Additional notes
 * @return bool|string True on success, error message on failure
 */
function recordOvertime($attendance_id, $overtime_hours, $overtime_rate = 1.5, $notes = null) {
    global $conn;
    
    // Check if record already exists
    $query = "SELECT overtime_id FROM overtime_records WHERE attendance_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // Update existing record
        $query = "UPDATE overtime_records 
                  SET overtime_hours = ?, overtime_rate = ?, notes = ?, updated_at = NOW()
                  WHERE overtime_id = ?";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ddsi", $overtime_hours, $overtime_rate, $notes, $existing['overtime_id']);
        
    } else {
        // Insert new record
        $query = "INSERT INTO overtime_records (attendance_id, overtime_hours, overtime_rate, notes)
                  VALUES (?, ?, ?, ?)";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("idds", $attendance_id, $overtime_hours, $overtime_rate, $notes);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        return "Error: " . $stmt->error;
    }
    
    $stmt->close();
    return true;
}

/**
 * Approve or reject overtime record
 * 
 * @param int $overtime_id Overtime record ID
 * @param string $status New status (approved/rejected)
 * @param int $approved_by User ID who approved this overtime
 * @param string $notes Additional notes
 * @return bool|string True on success, error message on failure
 */
function updateOvertimeStatus($overtime_id, $status, $approved_by, $notes = null) {
    global $conn;
    
    $query = "UPDATE overtime_records 
              SET status = ?, approved_by = ?, approval_date = NOW(), notes = ?, updated_at = NOW()
              WHERE overtime_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sisi", $status, $approved_by, $notes, $overtime_id);
    $result = $stmt->execute();
    
    if (!$result) {
        return "Error: " . $stmt->error;
    }
    
    $stmt->close();
    return true;
}

/**
 * Get attendance summary for a date range
 * 
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @param int $staff_id Optional staff ID filter
 * @return array Summary statistics
 */
function getAttendanceSummary($start_date, $end_date, $staff_id = null) {
    global $conn;
    
    $params = [];
    $types = "";
    
    $query = "SELECT 
                s.staff_id, s.first_name, s.last_name, s.staff_code, s.position,
                COUNT(ar.attendance_id) AS total_days,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) AS late_days,
                SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) AS half_days,
                SUM(CASE WHEN ar.status = 'leave' THEN 1 ELSE 0 END) AS leave_days,
                SUM(ar.hours_worked) AS total_hours,
                (SELECT SUM(ot.overtime_hours) FROM overtime_records ot 
                 JOIN attendance_records ar2 ON ot.attendance_id = ar2.attendance_id 
                 WHERE ar2.staff_id = s.staff_id AND ar2.attendance_date BETWEEN ? AND ? AND ot.status = 'approved'
                ) AS total_overtime
              FROM staff s
              LEFT JOIN attendance_records ar ON s.staff_id = ar.staff_id 
                                             AND ar.attendance_date BETWEEN ? AND ?
              WHERE s.status = 'active'";
    
    $types .= "ssss";
    $params[] = $start_date;
    $params[] = $end_date;
    $params[] = $start_date;
    $params[] = $end_date;
    
    if ($staff_id) {
        $query .= " AND s.staff_id = ?";
        $types .= "i";
        $params[] = $staff_id;
    }
    
    $query .= " GROUP BY s.staff_id, s.first_name, s.last_name, s.staff_code, s.position
                ORDER BY s.first_name, s.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $summary = [];
    while ($row = $result->fetch_assoc()) {
        $summary[] = $row;
    }
    
    $stmt->close();
    return $summary;
}

/**
 * Format time for display
 * 
 * @param string $time Time in datetime format
 * @return string Formatted time (H:i)
 */
function formatTime($time) {
    if (!$time) return '-';
    return date('H:i', strtotime($time));
}

/**
 * Format date for display
 * 
 * @param string $date Date in Y-m-d format
 * @return string Formatted date (d M, Y)
 */
function formatDate($date) {
    if (!$date) return '-';
    return date('d M, Y', strtotime($date));
}

/**
 * Calculate hours between two times
 * 
 * @param string $time_in Time in
 * @param string $time_out Time out
 * @return float Number of hours
 */
function calculateHours($time_in, $time_out) {
    if (!$time_in || !$time_out) return 0;
    
    $in = strtotime($time_in);
    $out = strtotime($time_out);
    
    if ($out <= $in) return 0;
    
    return round(($out - $in) / 3600, 2);
}