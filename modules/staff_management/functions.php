<?php
/**
 * Staff Management Module Functions
 * 
 * Helper functions for the staff management module
 */

/**
 * Get all staff members with optional filtering
 * 
 * @param object $conn Database connection
 * @param array $filters Optional filters for query
 * @return array Array of staff records
 */
function getAllStaff($conn, $filters = []) {
    $where_conditions = [];
    $params = [];
    $types = "";

    // Apply filters if provided
    if (!empty($filters)) {
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR staff_code LIKE ? OR phone LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= "ssss";
        }

        if (isset($filters['status']) && !empty($filters['status'])) {
            $where_conditions[] = "status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        if (isset($filters['position']) && !empty($filters['position'])) {
            $where_conditions[] = "position = ?";
            $params[] = $filters['position'];
            $types .= "s";
        }
    }

    // Build the where clause
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }

    // Base query
    $query = "SELECT * FROM staff $where_clause ORDER BY first_name, last_name";

    // Prepare and execute
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff_list = [];
    while ($row = $result->fetch_assoc()) {
        $staff_list[] = $row;
    }
    
    $stmt->close();
    return $staff_list;
}


/**
 * Remove a staff assignment
 * 
 * @param mysqli $conn Database connection
 * @param int $assignment_id Assignment ID to remove
 * @return bool True if successful, false otherwise
 */
function removeStaffAssignment($conn, $assignment_id) {
    $query = "DELETE FROM staff_assignments WHERE assignment_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $assignment_id);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}


/**
 * Get a single staff member by ID
 * 
 * @param object $conn Database connection
 * @param int $staff_id Staff ID
 * @return array|null Staff record or null if not found
 */
function getStaffById($conn, $staff_id) {
    $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $staff = $result->fetch_assoc();
        $stmt->close();
        return $staff;
    }
    
    $stmt->close();
    return null;
}

/**
 * Add a new staff member
 * 
 * @param object $conn Database connection
 * @param array $staff_data Staff data
 * @return int|false Staff ID if successful, false on failure
 */
/**
 * Add a new staff member
 * 
 * @param mysqli $conn Database connection
 * @param array $staff_data Staff data
 * @return bool|int Staff ID on success, false on failure
 */
function addStaff($conn, $staff_data) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into staff table
        $sql = "INSERT INTO staff (
                    staff_code, 
                    first_name, 
                    last_name, 
                    gender, 
                    date_of_birth, 
                    hire_date, 
                    position, 
                    department, 
                    phone, 
                    email, 
                    address, 
                    emergency_contact_name, 
                    emergency_contact_phone, 
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        // Handle null values for optional fields
        $date_of_birth = !empty($staff_data['date_of_birth']) ? $staff_data['date_of_birth'] : null;
        $department = !empty($staff_data['department']) ? $staff_data['department'] : null;
        $email = !empty($staff_data['email']) ? $staff_data['email'] : null;
        $address = !empty($staff_data['address']) ? $staff_data['address'] : null;
        $emergency_contact_name = !empty($staff_data['emergency_contact_name']) ? $staff_data['emergency_contact_name'] : null;
        $emergency_contact_phone = !empty($staff_data['emergency_contact_phone']) ? $staff_data['emergency_contact_phone'] : null;
        $notes = !empty($staff_data['notes']) ? $staff_data['notes'] : null;
        
        $stmt->bind_param(
            "ssssssssssssss",
            $staff_data['staff_code'],
            $staff_data['first_name'],
            $staff_data['last_name'],
            $staff_data['gender'],
            $date_of_birth,
            $staff_data['hire_date'],
            $staff_data['position'],
            $department,
            $staff_data['phone'],
            $email,
            $address,
            $emergency_contact_name,
            $emergency_contact_phone,
            $notes
        );
        
        $stmt->execute();
        
        if ($stmt->affected_rows <= 0) {
            // No rows affected, something went wrong
            throw new Exception("Failed to insert staff record");
        }
        
        $staff_id = $stmt->insert_id;
        
        // Create user account if requested
        if (isset($staff_data['create_account']) && $staff_data['create_account'] === 'yes') {
            // Generate username (first letter of first name + last name, lowercase)
            $username = strtolower(substr($staff_data['first_name'], 0, 1) . $staff_data['last_name']);
            
            // Generate random password
            $temp_password = generateRandomPassword();
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $sql = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            $full_name = $staff_data['first_name'] . ' ' . $staff_data['last_name'];
            $role = $staff_data['role'] ?? 'attendant';
            
            $stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
            $stmt->execute();
            
            if ($stmt->affected_rows <= 0) {
                // No rows affected, something went wrong
                throw new Exception("Failed to create user account");
            }
            
            $user_id = $stmt->insert_id;
            
            // Update staff record with user_id
            $sql = "UPDATE staff SET user_id = ? WHERE staff_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $staff_id);
            $stmt->execute();
            
            // Store temporary password for display
            $staff_data['temp_password'] = $temp_password;
        }
        
        // Commit transaction
        $conn->commit();
        
        return $staff_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error adding staff: " . $e->getMessage());
        return false;
    }
}


/**
 * Update a staff member
 * 
 * @param object $conn Database connection
 * @param int $staff_id Staff ID
 * @param array $staff_data Staff data
 * @return bool True on success, false on failure
 */
function updateStaff($conn, $staff_id, $staff_data) {
    $conn->begin_transaction();
    
    try {
        // Update staff table
        $stmt = $conn->prepare("
            UPDATE staff 
            SET first_name = ?, last_name = ?, gender = ?, date_of_birth = ?, 
                position = ?, department = ?, phone = ?, email = ?, address = ?,
                status = ?, emergency_contact_name = ?, emergency_contact_phone = ?, notes = ?
            WHERE staff_id = ?
        ");
        
        $stmt->bind_param(
            "sssssssssssssi",
            $staff_data['first_name'],
            $staff_data['last_name'],
            $staff_data['gender'],
            $staff_data['date_of_birth'],
            $staff_data['position'],
            $staff_data['department'],
            $staff_data['phone'],
            $staff_data['email'],
            $staff_data['address'],
            $staff_data['status'],
            $staff_data['emergency_contact_name'],
            $staff_data['emergency_contact_phone'],
            $staff_data['notes'],
            $staff_id
        );
        
        $stmt->execute();
        
        // If this staff member has a user account, update it too
        if (isset($staff_data['user_id']) && !empty($staff_data['user_id'])) {
            $full_name = $staff_data['first_name'] . ' ' . $staff_data['last_name'];
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET full_name = ?, email = ?
                WHERE user_id = ?
            ");
            
            $stmt->bind_param(
                "ssi",
                $full_name,
                $staff_data['email'],
                $staff_data['user_id']
            );
            
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error updating staff: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a staff member
 * 
 * @param object $conn Database connection
 * @param int $staff_id Staff ID
 * @return bool True on success, false on failure
 */
function deleteStaff($conn, $staff_id) {
    $conn->begin_transaction();
    
    try {
        // First check if this staff member has any dependencies
        // Get user_id if exists
        $stmt = $conn->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_id = null;
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $user_id = $row['user_id'];
        }
        
        // Delete from staff assignments
        $stmt = $conn->prepare("DELETE FROM staff_assignments WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        // Delete from staff performance
        $stmt = $conn->prepare("DELETE FROM staff_performance WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        // Delete from attendance records
        $stmt = $conn->prepare("DELETE FROM attendance_records WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        // Delete from daily cash records
        $stmt = $conn->prepare("DELETE FROM daily_cash_records WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        // Delete from sales
        $stmt = $conn->prepare("DELETE FROM sales WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        // Delete from staff table
        $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        
        // Delete the user account if it exists
        if ($user_id !== null) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error deleting staff: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all staff assignments
 * 
 * @param object $conn Database connection
 * @param string $date Date to get assignments for (YYYY-MM-DD)
 * @return array Array of staff assignments
 */
function getStaffAssignments($conn, $date = null) {
    // Default to today if no date provided
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $query = "
        SELECT sa.*, s.first_name, s.last_name, s.staff_code, p.pump_name
        FROM staff_assignments sa
        JOIN staff s ON sa.staff_id = s.staff_id
        JOIN pumps p ON sa.pump_id = p.pump_id
        WHERE sa.assignment_date = ?
        ORDER BY sa.shift, p.pump_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    
    $stmt->close();
    return $assignments;
}

/**
 * Assign staff to a pump
 * 
 * @param object $conn Database connection
 * @param array $assignment_data Assignment data
 * @return int|false Assignment ID if successful, false on failure
 */
function assignStaff($conn, $assignment_data) {
    try {
        // Check if an assignment already exists for this pump, date and shift
        $stmt = $conn->prepare("
            SELECT assignment_id FROM staff_assignments 
            WHERE pump_id = ? AND assignment_date = ? AND shift = ?
        ");
        $stmt->bind_param(
            "iss",
            $assignment_data['pump_id'],
            $assignment_data['assignment_date'],
            $assignment_data['shift']
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Assignment exists, update it
            $row = $result->fetch_assoc();
            $assignment_id = $row['assignment_id'];
            
            $stmt = $conn->prepare("
                UPDATE staff_assignments 
                SET staff_id = ?, status = ?, notes = ?
                WHERE assignment_id = ?
            ");
            $stmt->bind_param(
                "issi",
                $assignment_data['staff_id'],
                $assignment_data['status'],
                $assignment_data['notes'],
                $assignment_id
            );
            $stmt->execute();
            
            return $assignment_id;
        } else {
            // New assignment
            $stmt = $conn->prepare("
                INSERT INTO staff_assignments (staff_id, pump_id, assignment_date, shift, status, assigned_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iisssis",
                $assignment_data['staff_id'],
                $assignment_data['pump_id'],
                $assignment_data['assignment_date'],
                $assignment_data['shift'],
                $assignment_data['status'],
                $assignment_data['assigned_by'],
                $assignment_data['notes']
            );
            $stmt->execute();
            
            return $conn->insert_id;
        }
    } catch (Exception $e) {
        error_log("Error assigning staff: " . $e->getMessage());
        return false;
    }
}

/**
 * Get performance metrics for a staff member
 * 
 * @param object $conn Database connection
 * @param int $staff_id Staff ID
 * @param int $limit Number of records to return
 * @return array Array of performance records
 */
function getStaffPerformance($conn, $staff_id, $limit = 5) {
    $query = "
        SELECT * FROM staff_performance
        WHERE staff_id = ?
        ORDER BY evaluation_date DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $staff_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $performance = [];
    while ($row = $result->fetch_assoc()) {
        $performance[] = $row;
    }
    
    $stmt->close();
    return $performance;
}

/**
 * Get all active pumps for staff assignment
 * 
 * @param object $conn Database connection
 * @return array Array of active pumps
 */
function getActivePumps($conn) {
    $query = "
        SELECT p.*, t.tank_name, t.fuel_type_id, ft.fuel_name
        FROM pumps p
        JOIN tanks t ON p.tank_id = t.tank_id
        JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
        WHERE p.status = 'active'
        ORDER BY p.pump_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pumps = [];
    while ($row = $result->fetch_assoc()) {
        $pumps[] = $row;
    }
    
    $stmt->close();
    return $pumps;
}

/**
 * Get all active staff for assignment
 * 
 * @param object $conn Database connection
 * @return array Array of active staff
 */
function getActiveStaff($conn) {
    $query = "
        SELECT * FROM staff
        WHERE status = 'active'
        ORDER BY first_name, last_name
    ";
    
    $stmt = $conn->prepare($query);
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
 * Generate a unique staff code
 * 
 * @param object $conn Database connection
 * @param string $prefix Prefix for staff code (default: 'STF')
 * @return string Unique staff code
 */
function generateStaffCode($conn, $prefix = 'STF') {
    // Get the latest staff code with the given prefix
    $stmt = $conn->prepare("
        SELECT staff_code FROM staff 
        WHERE staff_code LIKE ? 
        ORDER BY staff_code DESC 
        LIMIT 1
    ");
    
    $like_prefix = $prefix . '%';
    $stmt->bind_param("s", $like_prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_code = $row['staff_code'];
        
        // Extract the number part
        $number_part = (int) substr($last_code, strlen($prefix));
        $new_number = $number_part + 1;
        
        // Format with leading zeros
        $new_code = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    } else {
        // No existing code, start with 0001
        $new_code = $prefix . '0001';
    }
    
    return $new_code;
}