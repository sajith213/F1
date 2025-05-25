<?php
/**
 * Salary Management Functions
 * 
 * This file contains the core functions for the salary management module including
 * salary calculations, loan management, and payslip generation.
 */

// Make sure we have database connection
if (!isset($conn)) {
    require_once('../../includes/db.php');
}

/**
 * Get employee salary information
 * 
 * @param int $staff_id Staff ID
 * @return array|null Salary information or null if not found
 */
function getEmployeeSalaryInfo($staff_id) {
    global $conn;
    
    $query = "SELECT * FROM employee_salary_info 
              WHERE staff_id = ? AND status = 'active' 
              ORDER BY effective_date DESC 
              LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $salary_info = $result->fetch_assoc();
        $stmt->close();
        return $salary_info;
    }
    
    $stmt->close();
    return null;
}

/**
 * Save employee salary information
 * 
 * @param array $data Salary data
 * @return bool|string True on success, error message on failure
 */
function saveEmployeeSalaryInfo($data) {
    global $conn;
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Check if there's already a salary record for this employee
        $check_query = "SELECT salary_info_id FROM employee_salary_info 
                        WHERE staff_id = ? AND status = 'active'";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $data['staff_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing records to inactive
            $update_query = "UPDATE employee_salary_info 
                            SET status = 'inactive', 
                                updated_by = ?, 
                                updated_at = NOW() 
                            WHERE staff_id = ? AND status = 'active'";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $data['updated_by'], $data['staff_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        $check_stmt->close();
        
        // Insert new salary record
        $insert_query = "INSERT INTO employee_salary_info (
                            staff_id, basic_salary, transport_allowance, 
                            meal_allowance, housing_allowance, other_allowance,
                            epf_employee_percent, epf_employer_percent, etf_percent,
                            paye_tax_percent, overtime_rate_regular, overtime_rate_holiday,
                            effective_date, status, created_by, updated_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )";
        
        $insert_stmt = $conn->prepare($insert_query);
        $status = 'active';
        $insert_stmt->bind_param(
            "idddddddddddssii",
            $data['staff_id'],
            $data['basic_salary'],
            $data['transport_allowance'],
            $data['meal_allowance'],
            $data['housing_allowance'],
            $data['other_allowance'],
            $data['epf_employee_percent'],
            $data['epf_employer_percent'],
            $data['etf_percent'],
            $data['paye_tax_percent'],
            $data['overtime_rate_regular'],
            $data['overtime_rate_holiday'],
            $data['effective_date'],
            $status,
            $data['created_by'],
            $data['updated_by']
        );
        
        $insert_stmt->execute();
        $insert_stmt->close();
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return "Error saving salary information: " . $e->getMessage();
    }
}
/**
 * Get all employees with their salary information
 * 
 * @return array Array of employees with salary info
 */
function getAllEmployeesWithSalaryInfo() {
    global $conn;
    
    // First check if the employee_salary_info table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'employee_salary_info'");
    $table_exists = $table_check->num_rows > 0;
    
    if ($table_exists) {
        // Table exists, use the join query
        $query = "SELECT s.*, esi.basic_salary, esi.effective_date 
                  FROM staff s 
                  LEFT JOIN (
                      SELECT staff_id, basic_salary, effective_date 
                      FROM employee_salary_info 
                      WHERE status = 'active' 
                      ORDER BY effective_date DESC
                  ) esi ON s.staff_id = esi.staff_id 
                  WHERE s.status = 'active'
                  GROUP BY s.staff_id
                  ORDER BY s.first_name, s.last_name";
    } else {
        // Table doesn't exist, just get staff info
        $query = "SELECT s.*, NULL as basic_salary, NULL as effective_date 
                  FROM staff s 
                  WHERE s.status = 'active'
                  ORDER BY s.first_name, s.last_name";
    }
    
    $result = $conn->query($query);
    
    $employees = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    
    return $employees;
}
/**
 * Calculate total salary budget
 * 
 * @return float Total salary budget
 */
function calculateTotalSalaryBudget() {
    global $conn;
    
    // Check if employee_salary_info table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'employee_salary_info'");
    $table_exists = $table_check->num_rows > 0;
    
    if (!$table_exists) {
        // Table doesn't exist, return 0
        return 0;
    }
    
    $query = "SELECT 
                SUM(basic_salary) as total_basic,
                SUM(transport_allowance) as total_transport,
                SUM(meal_allowance) as total_meal,
                SUM(housing_allowance) as total_housing,
                SUM(other_allowance) as total_other
              FROM employee_salary_info
              WHERE status = 'active'";
              
    $result = $conn->query($query);
    
    if ($result && $row = $result->fetch_assoc()) {
        // Calculate total budget
        $total_basic = $row['total_basic'] ?: 0;
        $total_transport = $row['total_transport'] ?: 0;
        $total_meal = $row['total_meal'] ?: 0;
        $total_housing = $row['total_housing'] ?: 0;
        $total_other = $row['total_other'] ?: 0;
        
        $total_budget = $total_basic + $total_transport + $total_meal + $total_housing + $total_other;
        
        return $total_budget;
    }
    
    return 0;
}
/**
 * Get count of pending salaries for a specific period
 * 
 * @param int $year Year
 * @param int $month Month
 * @return int Count of pending salaries
 */
function getCountOfPendingSalaries($year, $month) {
    global $conn;
    
    // Check if salary_records table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'salary_records'");
    $table_exists = $table_check->num_rows > 0;
    
    if (!$table_exists) {
        // Table doesn't exist, return 0
        return 0;
    }
    
    // Format pay period
    $pay_period = sprintf('%04d-%02d', $year, $month);
    
    $query = "SELECT COUNT(*) as count 
              FROM salary_records 
              WHERE pay_period = ? 
              AND payment_status = 'pending'";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return (int)$row['count'];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    return 0;
}
/**
 * Get count of completed salaries for a specific period
 * 
 * @param int $year Year
 * @param int $month Month
 * @return int Count of completed salaries
 */
function getCountOfCompletedSalaries($year, $month) {
    global $conn;
    
    // Check if salary_records table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'salary_records'");
    $table_exists = $table_check->num_rows > 0;
    
    if (!$table_exists) {
        // Table doesn't exist, return 0
        return 0;
    }
    
    // Format pay period
    $pay_period = sprintf('%04d-%02d', $year, $month);
    
    $query = "SELECT COUNT(*) as count 
              FROM salary_records 
              WHERE pay_period = ? 
              AND payment_status = 'paid'";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return (int)$row['count'];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    return 0;
}
/**
 * Get salary total for a specific period
 * 
 * @param int $year Year
 * @param int $month Month
 * @return float Total salary for the period
 */
function getSalaryTotalForPeriod($year, $month) {
    global $conn;
    
    // Check if salary_records table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'salary_records'");
    $table_exists = $table_check->num_rows > 0;
    
    if (!$table_exists) {
        // Table doesn't exist, return 0
        return 0;
    }
    
    // Format pay period
    $pay_period = sprintf('%04d-%02d', $year, $month);
    
    $query = "SELECT SUM(net_salary) as total 
              FROM salary_records 
              WHERE pay_period = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return (float)($row['total'] ?: 0);
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    return 0;
}
/**
 * Get count of salary records for a specific period
 * 
 * @param int $year Year
 * @param int $month Month
 * @return int Count of salary records
 */
function getSalaryCountForPeriod($year, $month) {
    global $conn;
    
    // Check if salary_records table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'salary_records'");
    $table_exists = $table_check->num_rows > 0;
    
    if (!$table_exists) {
        // Table doesn't exist, return 0
        return 0;
    }
    
    // Format pay period
    $pay_period = sprintf('%04d-%02d', $year, $month);
    
    $query = "SELECT COUNT(*) as count 
              FROM salary_records 
              WHERE pay_period = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return (int)$row['count'];
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    return 0;
}
/**
 * Get the status of a salary period
 * 
 * @param int $year Year
 * @param int $month Month
 * @return string Status (completed, in_progress, pending)
 */
function getSalaryPeriodStatus($year, $month) {
    global $conn;
    
    // Check if salary_records table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'salary_records'");
    $table_exists = $table_check->num_rows > 0;
    
    if (!$table_exists) {
        // Table doesn't exist, assume pending
        return 'pending';
    }
    
    // Format pay period
    $pay_period = sprintf('%04d-%02d', $year, $month);
    
    // Count total records
    $total_query = "SELECT COUNT(*) as total FROM salary_records WHERE pay_period = ?";
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param("s", $pay_period);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_count = (int)($total_row['total'] ?? 0);
    $total_stmt->close();
    
    if ($total_count == 0) {
        // No salary records for this period
        return 'pending';
    }
    
    // Count completed/paid records
    $paid_query = "SELECT COUNT(*) as paid FROM salary_records WHERE pay_period = ? AND payment_status = 'paid'";
    $paid_stmt = $conn->prepare($paid_query);
    $paid_stmt->bind_param("s", $pay_period);
    $paid_stmt->execute();
    $paid_result = $paid_stmt->get_result();
    $paid_row = $paid_result->fetch_assoc();
    $paid_count = (int)($paid_row['paid'] ?? 0);
    $paid_stmt->close();
    
    if ($paid_count == 0) {
        // No payments made yet
        return 'pending';
    } else if ($paid_count < $total_count) {
        // Some payments made but not all
        return 'in_progress';
    } else {
        // All payments complete
        return 'completed';
    }
}
/**
 * Get all active salary settings for an employee
 * 
 * @param int $staff_id Staff ID
 * @return array Array of salary settings
 */
function getAllEmployeeSalarySettings($staff_id) {
    global $conn;
    
    $query = "SELECT * FROM employee_salary_info 
              WHERE staff_id = ? 
              ORDER BY effective_date DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[] = $row;
    }
    
    $stmt->close();
    return $settings;
}

/**
 * Get employee loans
 * 
 * @param int $staff_id Staff ID
 * @param string $status Loan status filter (active, completed, cancelled, all)
 * @return array Array of loans
 */
function getEmployeeLoans($staff_id, $status = 'all') {
    global $conn;
    
    // First check if the employee_loans table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'employee_loans'");
    $table_exists = $table_check->num_rows > 0;
    
    // If table doesn't exist, return empty array
    if (!$table_exists) {
        return [];
    }
    
    // Original function code
    $query = "SELECT * FROM employee_loans WHERE staff_id = ?";
    
    if ($status !== 'all') {
        $query .= " AND status = ?";
    }
    
    $query .= " ORDER BY start_date DESC";
    
    $stmt = $conn->prepare($query);
    
    if ($status !== 'all') {
        $stmt->bind_param("is", $staff_id, $status);
    } else {
        $stmt->bind_param("i", $staff_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    
    $stmt->close();
    return $loans;
}
/**
 * Add new employee loan
 * 
 * @param array $loan_data Loan data
 * @return bool|string True on success, error message on failure
 */
function addEmployeeLoan($loan_data) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        $query = "INSERT INTO employee_loans (
                    staff_id, loan_amount, remaining_amount, 
                    monthly_deduction, start_date, end_date, 
                    loan_type, status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $status = 'active';
        $stmt->bind_param(
            "idddsssssi",
            $loan_data['staff_id'],
            $loan_data['loan_amount'],
            $loan_data['loan_amount'], // Initially remaining = total
            $loan_data['monthly_deduction'],
            $loan_data['start_date'],
            $loan_data['end_date'],
            $loan_data['loan_type'],
            $status,
            $loan_data['notes'],
            $loan_data['created_by']
        );
        
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return "Error adding loan: " . $e->getMessage();
    }
}

/**
 * Update loan payment
 * 
 * @param int $loan_id Loan ID
 * @param float $payment_amount Payment amount
 * @param int $user_id User making the update
 * @return bool|string True on success, error message on failure
 */
function updateLoanPayment($loan_id, $payment_amount, $user_id) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Get current loan data
        $query = "SELECT * FROM employee_loans WHERE loan_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->rollback();
            return "Loan not found";
        }
        
        $loan = $result->fetch_assoc();
        $stmt->close();
        
        // Calculate new remaining amount
        $new_remaining = $loan['remaining_amount'] - $payment_amount;
        if ($new_remaining < 0) {
            $new_remaining = 0;
        }
        
        // Update loan record
        $update_query = "UPDATE employee_loans 
                        SET remaining_amount = ?,
                            status = IF(? <= 0, 'completed', status),
                            updated_at = NOW()
                        WHERE loan_id = ?";
                        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ddi", $new_remaining, $new_remaining, $loan_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return "Error updating loan payment: " . $e->getMessage();
    }
}

/**
 * Calculate monthly salary
 * 
 * @param int $staff_id Staff ID
 * @param string $pay_period Pay period in format YYYY-MM
 * @param int $user_id User calculating the salary
 * @return array|string Calculated salary data or error message
 */
function calculateMonthlySalary($staff_id, $pay_period, $user_id) {
    global $conn;
    
    try {
        // Get the year and month from pay period
        list($year, $month) = explode('-', $pay_period);
        $last_day = date('t', strtotime($pay_period . '-01'));
        $start_date = "$year-$month-01";
        $end_date = "$year-$month-$last_day";
        
        // Get employee salary info
        $salary_info = getEmployeeSalaryInfo($staff_id);
        if (!$salary_info) {
            return "No salary configuration found for this employee";
        }
        
        // Get employee attendance data
        $attendance_query = "SELECT 
                              COUNT(CASE WHEN status IN ('present', 'half_day') THEN 1 END) as days_worked,
                              COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days,
                              COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                              SUM(CASE WHEN status = 'half_day' THEN 0.5 ELSE 0 END) as half_days
                            FROM attendance_records 
                            WHERE staff_id = ? 
                            AND attendance_date BETWEEN ? AND ?";
                            
        $att_stmt = $conn->prepare($attendance_query);
        $att_stmt->bind_param("iss", $staff_id, $start_date, $end_date);
        $att_stmt->execute();
        $att_result = $att_stmt->get_result();
        $attendance = $att_result->fetch_assoc();
        $att_stmt->close();
        
        // Get overtime hours
        $overtime_query = "SELECT 
                            SUM(overtime_hours) as total_overtime,
                            SUM(overtime_amount) as overtime_amount
                          FROM overtime_records or1
                          JOIN attendance_records ar ON or1.attendance_id = ar.attendance_id
                          WHERE ar.staff_id = ? 
                          AND ar.attendance_date BETWEEN ? AND ?
                          AND or1.status = 'approved'";
                          
        $ot_stmt = $conn->prepare($overtime_query);
        $ot_stmt->bind_param("iss", $staff_id, $start_date, $end_date);
        $ot_stmt->execute();
        $ot_result = $ot_stmt->get_result();
        $overtime = $ot_result->fetch_assoc();
        $ot_stmt->close();
        
        // Get active loans
        $loans = getEmployeeLoans($staff_id, 'active');
        $loan_deductions = 0;
        foreach ($loans as $loan) {
            $loan_deductions += $loan['monthly_deduction'];
        }
        
        // Calculate basic salary (adjusted for unpaid leave/absence if applicable)
        $working_days = $attendance['days_worked'] ?: 0;
        $leave_days = $attendance['leave_days'] ?: 0;
        $absent_days = $attendance['absent_days'] ?: 0;
        $half_days = $attendance['half_days'] ?: 0;
        
        // Adjust working days for half days
        $working_days = $working_days - $half_days;
        
        // Calculate total days in month
        $total_working_days = intval($last_day); // Using calendar days
        
        // If no attendance records, assume full month
        if (($working_days + $leave_days + $absent_days) == 0) {
            $working_days = $total_working_days;
        }
        
        // Calculate adjusted basic salary
        $basic_salary = $salary_info['basic_salary'];
        
        // Calculate allowances
        $transport_allowance = $salary_info['transport_allowance'] ?: 0;
        $meal_allowance = $salary_info['meal_allowance'] ?: 0;
        $housing_allowance = $salary_info['housing_allowance'] ?: 0;
        $other_allowance = $salary_info['other_allowance'] ?: 0;
        
        // Calculate overtime amount if not already calculated
        $overtime_hours = $overtime['total_overtime'] ?: 0;
        $overtime_amount = $overtime['overtime_amount'] ?: 0;
        
        if ($overtime_hours > 0 && $overtime_amount == 0) {
            // Calculate using hourly rate and overtime multiplier
            $hourly_rate = $basic_salary / (8 * 22); // Assuming 8 hours/day, 22 days/month
            $overtime_amount = $overtime_hours * $hourly_rate * $salary_info['overtime_rate_regular'];
        }
        
        // Calculate gross salary
        $gross_salary = $basic_salary + $transport_allowance + $meal_allowance + 
                        $housing_allowance + $other_allowance + $overtime_amount;
        
        // Calculate deductions
        $epf_employee = ($basic_salary * $salary_info['epf_employee_percent']) / 100;
        $epf_employer = ($basic_salary * $salary_info['epf_employer_percent']) / 100;
        $etf = ($basic_salary * $salary_info['etf_percent']) / 100;
        
        // Calculate PAYE tax if applicable
        $paye_tax = 0;
        if ($salary_info['paye_tax_percent'] > 0) {
            $paye_tax = ($gross_salary * $salary_info['paye_tax_percent']) / 100;
        }
        
        // Other deductions can be added here
        $other_deductions = 0;
        
        // Calculate total deductions
        $total_deductions = $epf_employee + $paye_tax + $loan_deductions + $other_deductions;
        
        // Calculate net salary
        $net_salary = $gross_salary - $total_deductions;
        
        // Prepare salary record
        $salary_data = [
            'staff_id' => $staff_id,
            'pay_period' => $pay_period,
            'basic_salary' => $basic_salary,
            'transport_allowance' => $transport_allowance,
            'meal_allowance' => $meal_allowance,
            'housing_allowance' => $housing_allowance,
            'other_allowance' => $other_allowance,
            'overtime_hours' => $overtime_hours,
            'overtime_amount' => $overtime_amount,
            'gross_salary' => $gross_salary,
            'epf_employee' => $epf_employee,
            'epf_employer' => $epf_employer,
            'etf' => $etf,
            'paye_tax' => $paye_tax,
            'loan_deductions' => $loan_deductions,
            'other_deductions' => $other_deductions,
            'total_deductions' => $total_deductions,
            'net_salary' => $net_salary,
            'days_worked' => $working_days,
            'leave_days' => $leave_days,
            'absent_days' => $absent_days,
            'payment_status' => 'pending',
            'calculated_by' => $user_id
        ];
        
        return $salary_data;
    } catch (Exception $e) {
        return "Error calculating salary: " . $e->getMessage();
    }
}

/**
 * Save calculated salary to database
 * 
 * @param array $salary_data Calculated salary data
 * @return bool|string True on success, error message on failure
 */
function saveSalaryRecord($salary_data) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Check if there's already a record for this period
        $check_query = "SELECT salary_id FROM salary_records 
                       WHERE staff_id = ? AND pay_period = ?";
                       
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $salary_data['staff_id'], $salary_data['pay_period']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $row = $check_result->fetch_assoc();
            $salary_id = $row['salary_id'];
            
            $update_query = "UPDATE salary_records SET
                            basic_salary = ?,
                            transport_allowance = ?,
                            meal_allowance = ?,
                            housing_allowance = ?,
                            other_allowance = ?,
                            overtime_hours = ?,
                            overtime_amount = ?,
                            gross_salary = ?,
                            epf_employee = ?,
                            epf_employer = ?,
                            etf = ?,
                            paye_tax = ?,
                            loan_deductions = ?,
                            other_deductions = ?,
                            total_deductions = ?,
                            net_salary = ?,
                            days_worked = ?,
                            leave_days = ?,
                            absent_days = ?,
                            calculated_by = ?,
                            updated_at = NOW()
                        WHERE salary_id = ?";
                        
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param(
                "ddddddddddddddddiiii",
                $salary_data['basic_salary'],
                $salary_data['transport_allowance'],
                $salary_data['meal_allowance'],
                $salary_data['housing_allowance'],
                $salary_data['other_allowance'],
                $salary_data['overtime_hours'],
                $salary_data['overtime_amount'],
                $salary_data['gross_salary'],
                $salary_data['epf_employee'],
                $salary_data['epf_employer'],
                $salary_data['etf'],
                $salary_data['paye_tax'],
                $salary_data['loan_deductions'],
                $salary_data['other_deductions'],
                $salary_data['total_deductions'],
                $salary_data['net_salary'],
                $salary_data['days_worked'],
                $salary_data['leave_days'],
                $salary_data['absent_days'],
                $salary_data['calculated_by'],
                $salary_id
            );
        } else {
            // Insert new record
            $insert_query = "INSERT INTO salary_records (
                            staff_id, pay_period, basic_salary,
                            transport_allowance, meal_allowance, housing_allowance,
                            other_allowance, overtime_hours, overtime_amount,
                            gross_salary, epf_employee, epf_employer,
                            etf, paye_tax, loan_deductions,
                            other_deductions, total_deductions, net_salary,
                            days_worked, leave_days, absent_days,
                            payment_status, calculated_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )";
                        
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param(
                "isdddddddddddddddiiiisi",
                $salary_data['staff_id'],
                $salary_data['pay_period'],
                $salary_data['basic_salary'],
                $salary_data['transport_allowance'],
                $salary_data['meal_allowance'],
                $salary_data['housing_allowance'],
                $salary_data['other_allowance'],
                $salary_data['overtime_hours'],
                $salary_data['overtime_amount'],
                $salary_data['gross_salary'],
                $salary_data['epf_employee'],
                $salary_data['epf_employer'],
                $salary_data['etf'],
                $salary_data['paye_tax'],
                $salary_data['loan_deductions'],
                $salary_data['other_deductions'],
                $salary_data['total_deductions'],
                $salary_data['net_salary'],
                $salary_data['days_worked'],
                $salary_data['leave_days'],
                $salary_data['absent_days'],
                $salary_data['payment_status'],
                $salary_data['calculated_by']
            );
        }
        
        $check_stmt->close();
        
        $stmt->execute();
        $salary_id = $stmt->insert_id ?: $salary_id;
        $stmt->close();
        
        // Update loan remaining amounts if applicable
        if ($salary_data['loan_deductions'] > 0) {
            $loans = getEmployeeLoans($salary_data['staff_id'], 'active');
            foreach ($loans as $loan) {
                $deduction = min($loan['monthly_deduction'], $loan['remaining_amount']);
                $new_remaining = $loan['remaining_amount'] - $deduction;
                
                $loan_status = $new_remaining <= 0 ? 'completed' : 'active';
                
                $update_loan_query = "UPDATE employee_loans SET
                                    remaining_amount = ?,
                                    status = ?,
                                    updated_at = NOW()
                                WHERE loan_id = ?";
                                
                $loan_stmt = $conn->prepare($update_loan_query);
                $loan_stmt->bind_param("dsi", $new_remaining, $loan_status, $loan['loan_id']);
                $loan_stmt->execute();
                $loan_stmt->close();
            }
        }
        
        $conn->commit();
        return $salary_id;
    } catch (Exception $e) {
        $conn->rollback();
        return "Error saving salary record: " . $e->getMessage();
    }
}

/**
 * Get calculated salary records for a period
 * 
 * @param string $pay_period Pay period in format YYYY-MM
 * @return array Array of salary records
 */
function getSalaryRecords($pay_period) {
    global $conn;
    
    $query = "SELECT sr.*, s.first_name, s.last_name, s.staff_code, s.position
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period = ?
              ORDER BY s.first_name, s.last_name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
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
 * Get single salary record
 * 
 * @param int $salary_id Salary ID
 * @return array|null Salary record or null if not found
 */
function getSalaryRecord($salary_id) {
    global $conn;
    
    $query = "SELECT sr.*, s.first_name, s.last_name, s.staff_code, s.position
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.salary_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $salary_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        $stmt->close();
        return $record;
    }
    
    $stmt->close();
    return null;
}

/**
 * Record salary payment
 * 
 * @param int $salary_id Salary ID
 * @param array $payment_data Payment data
 * @return bool|string True on success, error message on failure
 */
function recordSalaryPayment($salary_id, $payment_data) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Get salary record
        $salary = getSalaryRecord($salary_id);
        if (!$salary) {
            return "Salary record not found";
        }
        
        // Insert payment record
        $insert_query = "INSERT INTO salary_payments (
                        salary_id, payment_date, amount,
                        payment_method, reference_no, notes, processed_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param(
            "isdssis",
            $salary_id,
            $payment_data['payment_date'],
            $payment_data['amount'],
            $payment_data['payment_method'],
            $payment_data['reference_no'],
            $payment_data['notes'],
            $payment_data['processed_by']
        );
        
        $insert_stmt->execute();
        $insert_stmt->close();
        
        // Update salary record status
        $update_query = "UPDATE salary_records SET
                        payment_status = ?,
                        payment_date = ?,
                        payment_method = ?,
                        reference_no = ?,
                        updated_at = NOW()
                    WHERE salary_id = ?";
                    
        $update_stmt = $conn->prepare($update_query);
        $status = 'paid';
        $update_stmt->bind_param(
            "ssssi",
            $status,
            $payment_data['payment_date'],
            $payment_data['payment_method'],
            $payment_data['reference_no'],
            $salary_id
        );
        
        $update_stmt->execute();
        $update_stmt->close();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return "Error recording payment: " . $e->getMessage();
    }
}

/**
 * Get EPF/ETF report data
 * 
 * @param string $year_month Year and month in format YYYY-MM
 * @return array Report data
 */
function getEpfEtfReportData($year_month) {
    global $conn;
    
    $query = "SELECT 
                sr.staff_id,
                s.first_name,
                s.last_name,
                s.staff_code,
                sr.basic_salary,
                sr.epf_employee,
                sr.epf_employer,
                sr.etf,
                (sr.epf_employee + sr.epf_employer) as total_epf,
                sr.pay_period
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period = ?
              ORDER BY s.first_name, s.last_name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $year_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $report_data = [];
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    
    $stmt->close();
    
    // Calculate totals
    $totals = [
        'basic_salary' => 0,
        'epf_employee' => 0,
        'epf_employer' => 0,
        'etf' => 0,
        'total_epf' => 0
    ];
    
    foreach ($report_data as $row) {
        $totals['basic_salary'] += $row['basic_salary'];
        $totals['epf_employee'] += $row['epf_employee'];
        $totals['epf_employer'] += $row['epf_employer'];
        $totals['etf'] += $row['etf'];
        $totals['total_epf'] += $row['total_epf'];
    }
    
    return [
        'records' => $report_data,
        'totals' => $totals
    ];
}

/**
 * Get salary summary report
 * 
 * @param string $year_month Year and month in format YYYY-MM
 * @param string $department Department filter (optional)
 * @return array Report data
 */
function getSalarySummaryReport($year_month, $department = null) {
    global $conn;
    
    $query = "SELECT 
                s.department,
                COUNT(sr.salary_id) as employee_count,
                SUM(sr.basic_salary) as total_basic,
                SUM(sr.transport_allowance) as total_transport,
                SUM(sr.meal_allowance) as total_meal,
                SUM(sr.housing_allowance) as total_housing,
                SUM(sr.other_allowance) as total_other_allowance,
                SUM(sr.overtime_amount) as total_overtime,
                SUM(sr.gross_salary) as total_gross,
                SUM(sr.epf_employee) as total_epf_employee,
                SUM(sr.epf_employer) as total_epf_employer,
                SUM(sr.etf) as total_etf,
                SUM(sr.paye_tax) as total_tax,
                SUM(sr.loan_deductions) as total_loans,
                SUM(sr.other_deductions) as total_other_deductions,
                SUM(sr.net_salary) as total_net
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period = ?";
              
    $params = [$year_month];
    $types = "s";
    
    if ($department) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    $query .= " GROUP BY s.department
                ORDER BY s.department";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $report_data = [];
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    
    $stmt->close();
    
    // Calculate grand totals
    $grand_totals = [
        'employee_count' => 0,
        'total_basic' => 0,
        'total_transport' => 0,
        'total_meal' => 0,
        'total_housing' => 0,
        'total_other_allowance' => 0,
        'total_overtime' => 0,
        'total_gross' => 0,
        'total_epf_employee' => 0,
        'total_epf_employer' => 0,
        'total_etf' => 0,
        'total_tax' => 0,
        'total_loans' => 0,
        'total_other_deductions' => 0,
        'total_net' => 0
    ];
    
    foreach ($report_data as $row) {
        foreach ($grand_totals as $key => $value) {
            $grand_totals[$key] += $row[$key];
        }
    }
    
    return [
        'departments' => $report_data,
        'grand_totals' => $grand_totals
    ];
}

/**
 * Calculate Year-to-Date (YTD) totals for an employee
 * 
 * @param int $staff_id Staff ID
 * @param int $year Year
 * @return array YTD totals
 */
function calculateYearToDateTotals($staff_id, $year) {
    global $conn;
    
    $query = "SELECT 
                SUM(gross_salary) as ytd_gross,
                SUM(basic_salary) as ytd_basic,
                SUM(transport_allowance) as ytd_transport,
                SUM(meal_allowance) as ytd_meal,
                SUM(housing_allowance) as ytd_housing,
                SUM(other_allowance) as ytd_other,
                SUM(overtime_amount) as ytd_overtime,
                SUM(epf_employee) as ytd_epf_employee,
                SUM(epf_employer) as ytd_epf_employer,
                SUM(etf) as ytd_etf,
                SUM(paye_tax) as ytd_tax,
                SUM(loan_deductions) as ytd_loans,
                SUM(other_deductions) as ytd_other_deductions,
                SUM(net_salary) as ytd_net
              FROM salary_records
              WHERE staff_id = ? AND pay_period LIKE ?";
              
    $stmt = $conn->prepare($query);
    $year_like = $year . '-%';
    $stmt->bind_param("is", $staff_id, $year_like);
    $stmt->execute();
    $result = $stmt->get_result();
    $ytd_data = $result->fetch_assoc();
    
    $stmt->close();
    
    return $ytd_data;
}

/**
 * Get employee annual earnings statement
 * 
 * @param int $staff_id Staff ID
 * @param int $year Year
 * @return array Annual earnings data
 */
function getAnnualEarningsStatement($staff_id, $year) {
    global $conn;
    
    $query = "SELECT 
                sr.*,
                s.first_name,
                s.last_name,
                s.staff_code,
                s.position,
                s.department
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.staff_id = ? AND sr.pay_period LIKE ?
              ORDER BY sr.pay_period";
              
    $stmt = $conn->prepare($query);
    $year_like = $year . '-%';
    $stmt->bind_param("is", $staff_id, $year_like);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthly_records = [];
    while ($row = $result->fetch_assoc()) {
        // Extract month from pay_period
        $month = substr($row['pay_period'], 5, 2);
        $monthly_records[$month] = $row;
    }
    
    $stmt->close();
    
    // Get YTD totals
    $ytd_totals = calculateYearToDateTotals($staff_id, $year);
    
    // Get employee details
    $employee_query = "SELECT * FROM staff WHERE staff_id = ?";
    $emp_stmt = $conn->prepare($employee_query);
    $emp_stmt->bind_param("i", $staff_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    $employee = $emp_result->fetch_assoc();
    $emp_stmt->close();
    
    return [
        'employee' => $employee,
        'monthly_records' => $monthly_records,
        'ytd_totals' => $ytd_totals,
        'year' => $year
    ];
}

/**
 * Process batch salary calculations for a pay period
 * 
 * @param string $pay_period Pay period in format YYYY-MM
 * @param int $user_id User processing the salaries
 * @return array Result with success count and errors
 */
function processBatchSalaries($pay_period, $user_id) {
    global $conn;
    
    // Get all active employees
    $query = "SELECT staff_id FROM staff WHERE status = 'active'";
    $result = $conn->query($query);
    
    $success_count = 0;
    $errors = [];
    
    while ($row = $result->fetch_assoc()) {
        $staff_id = $row['staff_id'];
        
        // Calculate salary
        $salary_data = calculateMonthlySalary($staff_id, $pay_period, $user_id);
        
        if (is_array($salary_data)) {
            // Save salary record
            $save_result = saveSalaryRecord($salary_data);
            
            if (is_numeric($save_result)) {
                $success_count++;
            } else {
                $errors[] = "Error processing staff ID $staff_id: $save_result";
            }
        } else {
            $errors[] = "Error calculating salary for staff ID $staff_id: $salary_data";
        }
    }
    
    return [
        'success_count' => $success_count,
        'errors' => $errors
    ];
}

/**
 * Convert number to words (for payslip)
 *
 * @param float $number The number to convert
 * @return string The number in words
 */
function getWords($number) {
    $ones = array(
        0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen'
    );

    $tens = array(
        0 => '', 1 => 'ten', 2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'
    );

    $number = number_format($number, 2, '.', '');
    $number_parts = explode('.', $number);
    $whole_number = $number_parts[0];
    $decimal = $number_parts[1];

    // Get whole number in words
    if ($whole_number == 0) {
        $whole_words = 'zero';
    } else {
        $whole_words = '';

        // Handle millions
        if ($whole_number >= 1000000 && $whole_number < 1000000000) {
            $millions = (int)($whole_number / 1000000);
            // Recursive call needed here for millions part
            $whole_words .= getWords($millions) . ' million ';
            $whole_number -= $millions * 1000000;
        }

        // Handle thousands
        if ($whole_number >= 1000 && $whole_number < 1000000) {
            $thousands = (int)($whole_number / 1000);
             // Recursive call needed here for thousands part
            $whole_words .= getWords($thousands) . ' thousand ';
            $whole_number -= $thousands * 1000;
        }

        // Handle hundreds
        if ($whole_number >= 100 && $whole_number < 1000) {
            $hundreds = (int)($whole_number / 100);
            $whole_words .= $ones[$hundreds] . ' hundred ';
            $whole_number -= $hundreds * 100;
        }

        // Handle tens and ones
        if ($whole_number > 0) {
            // Add 'and' only if there were millions, thousands, or hundreds
            if (trim($whole_words) != '') {
                $whole_words .= 'and ';
            }

            if ($whole_number < 20) {
                $whole_words .= $ones[$whole_number];
            } else {
                $whole_words .= $tens[(int)($whole_number / 10)];
                if ($whole_number % 10 > 0) {
                    $whole_words .= '-' . $ones[$whole_number % 10];
                }
            }
        }
    }

    // Get cents in words
    if ($decimal == '00') {
        return ucfirst(trim($whole_words));
    } else {
        $cents_words = '';
        $decimal_num = (int)$decimal;

        if ($decimal_num < 20) {
            $cents_words = $ones[$decimal_num];
        } else {
            $cents_words = $tens[(int)($decimal_num / 10)];
            if ($decimal_num % 10 > 0) {
                $cents_words .= '-' . $ones[$decimal_num % 10];
            }
        }

        return ucfirst(trim($whole_words)) . ' and ' . $cents_words . ' cents';
    }
}

/**
 * Get loan summary report
 * 
 * @param string $status Loan status filter (active, completed, cancelled, all)
 * @return array Loan summary data
 */
function getLoanSummaryReport($status = 'all') {
    global $conn;
    
    // First check if the employee_loans table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'employee_loans'");
    $table_exists = $table_check->num_rows > 0;
    
    // Initialize default return values
    $loans = [];
    $summary = [
        'total_loans' => 0,
        'total_amount' => 0,
        'total_remaining' => 0,
        'total_paid' => 0,
        'active_loans' => 0,
        'completed_loans' => 0,
        'cancelled_loans' => 0
    ];
    
    // If table doesn't exist, return empty data
    if (!$table_exists) {
        return [
            'loans' => $loans,
            'summary' => $summary
        ];
    }
    
    // Original function code
    $query = "SELECT 
                el.*,
                s.first_name,
                s.last_name,
                s.staff_code,
                s.position,
                s.department,
                (el.loan_amount - el.remaining_amount) as amount_paid,
                CASE 
                    WHEN el.remaining_amount > 0 THEN 
                        CEIL(el.remaining_amount / el.monthly_deduction)
                    ELSE 0
                END as remaining_installments
              FROM employee_loans el
              JOIN staff s ON el.staff_id = s.staff_id";
              
    if ($status !== 'all') {
        $query .= " WHERE el.status = ?";
    }
    
    $query .= " ORDER BY el.start_date DESC";
    
    if ($status !== 'all') {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $status);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    
    $stmt->close();
    
    // Calculate summary statistics
    $summary = [
        'total_loans' => count($loans),
        'total_amount' => 0,
        'total_remaining' => 0,
        'total_paid' => 0,
        'active_loans' => 0,
        'completed_loans' => 0,
        'cancelled_loans' => 0
    ];
    
    foreach ($loans as $loan) {
        $summary['total_amount'] += $loan['loan_amount'];
        $summary['total_remaining'] += $loan['remaining_amount'];
        $summary['total_paid'] += $loan['amount_paid'];
        
        if ($loan['status'] === 'active') {
            $summary['active_loans']++;
        } elseif ($loan['status'] === 'completed') {
            $summary['completed_loans']++;
        } elseif ($loan['status'] === 'cancelled') {
            $summary['cancelled_loans']++;
        }
    }
    
    return [
        'loans' => $loans,
        'summary' => $summary
    ];
}

/**
 * Generate monthly Payroll Register report
 * 
 * @param string $pay_period Pay period in format YYYY-MM
 * @return array Payroll register data
 */
function generatePayrollRegister($pay_period) {
    global $conn;
    
    $query = "SELECT 
                sr.*,
                s.first_name,
                s.last_name,
                s.staff_code,
                s.position,
                s.department
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period = ?
              ORDER BY s.department, s.first_name, s.last_name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    $stmt->close();
    
    // Calculate department and grand totals
    $department_totals = [];
    $grand_totals = [
        'basic_salary' => 0,
        'transport_allowance' => 0,
        'meal_allowance' => 0,
        'housing_allowance' => 0,
        'other_allowance' => 0,
        'overtime_amount' => 0,
        'gross_salary' => 0,
        'epf_employee' => 0,
        'epf_employer' => 0,
        'etf' => 0,
        'paye_tax' => 0,
        'loan_deductions' => 0,
        'other_deductions' => 0,
        'total_deductions' => 0,
        'net_salary' => 0
    ];
    
    foreach ($records as $record) {
        $dept = $record['department'] ?: 'Unassigned';
        
        if (!isset($department_totals[$dept])) {
            $department_totals[$dept] = array_fill_keys(array_keys($grand_totals), 0);
        }
        
        foreach ($grand_totals as $key => $value) {
            $department_totals[$dept][$key] += $record[$key];
            $grand_totals[$key] += $record[$key];
        }
    }
    
    return [
        'records' => $records,
        'department_totals' => $department_totals,
        'grand_totals' => $grand_totals,
        'pay_period' => $pay_period
    ];
}

/**
 * Check if salary has been processed for a pay period
 * 
 * @param string $pay_period Pay period in format YYYY-MM
 * @return bool True if salary has been processed
 */
function isSalaryProcessed($pay_period) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM salary_records WHERE pay_period = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    
    return ($row['count'] > 0);
}

/**
 * Get attendance summary for a pay period
 * 
 * @param int $staff_id Staff ID
 * @param string $pay_period Pay period in format YYYY-MM
 * @return array Attendance summary
 */
function getAttendanceSummary($staff_id, $pay_period) {
    global $conn;
    
    // Parse the pay period to extract year and month
    list($year, $month) = explode('-', $pay_period);
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $query = "SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
                COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days,
                SUM(hours_worked) as total_hours
              FROM attendance_records
              WHERE staff_id = ? 
              AND attendance_date BETWEEN ? AND ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $staff_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    
    $stmt->close();
    
    // Get overtime details
    $overtime_query = "SELECT 
                        SUM(or1.overtime_hours) as total_overtime,
                        COUNT(or1.overtime_id) as overtime_count
                      FROM overtime_records or1
                      JOIN attendance_records ar ON or1.attendance_id = ar.attendance_id
                      WHERE ar.staff_id = ? 
                      AND ar.attendance_date BETWEEN ? AND ?
                      AND or1.status = 'approved'";
                      
    $ot_stmt = $conn->prepare($overtime_query);
    $ot_stmt->bind_param("iss", $staff_id, $start_date, $end_date);
    $ot_stmt->execute();
    $ot_result = $ot_stmt->get_result();
    $overtime = $ot_result->fetch_assoc();
    
    $ot_stmt->close();
    
    // Combine data
    $summary['total_overtime'] = $overtime['total_overtime'] ?: 0;
    $summary['overtime_count'] = $overtime['overtime_count'] ?: 0;
    
    return $summary;
}

/**
 * Get bank transfer list for salary payments
 * 
 * @param string $pay_period Pay period in format YYYY-MM
 * @return array Bank transfer data
 */
function getBankTransferList($pay_period) {
    global $conn;
    
    // Check if there is a staff_bank_details table
    $table_check = $conn->query("SHOW TABLES LIKE 'staff_bank_details'");
    $has_bank_details = $table_check->num_rows > 0;
    
    if ($has_bank_details) {
        $query = "SELECT 
                    sr.salary_id,
                    sr.staff_id,
                    s.first_name,
                    s.last_name,
                    s.staff_code,
                    sbd.bank_name,
                    sbd.branch_name,
                    sbd.account_number,
                    sbd.account_name,
                    sr.net_salary
                  FROM salary_records sr
                  JOIN staff s ON sr.staff_id = s.staff_id
                  LEFT JOIN staff_bank_details sbd ON s.staff_id = sbd.staff_id
                  WHERE sr.pay_period = ? AND sr.payment_status = 'pending'
                  ORDER BY s.first_name, s.last_name";
    } else {
        // Simplified query without bank details
        $query = "SELECT 
                    sr.salary_id,
                    sr.staff_id,
                    s.first_name,
                    s.last_name,
                    s.staff_code,
                    sr.net_salary
                  FROM salary_records sr
                  JOIN staff s ON sr.staff_id = s.staff_id
                  WHERE sr.pay_period = ? AND sr.payment_status = 'pending'
                  ORDER BY s.first_name, s.last_name";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $pay_period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transfer_list = [];
    $total_amount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $transfer_list[] = $row;
        $total_amount += $row['net_salary'];
    }
    
    $stmt->close();
    
    return [
        'transfers' => $transfer_list,
        'total_amount' => $total_amount,
        'has_bank_details' => $has_bank_details
    ];
}

/**
 * Record batch salary payments
 * 
 * @param array $salary_ids Array of salary IDs
 * @param array $payment_data Payment data
 * @return array Result with success count and errors
 */
function recordBatchSalaryPayments($salary_ids, $payment_data) {
    global $conn;
    
    $success_count = 0;
    $errors = [];
    
    try {
        $conn->begin_transaction();
        
        foreach ($salary_ids as $salary_id) {
            // Insert payment record
            $insert_query = "INSERT INTO salary_payments (
                            salary_id, payment_date, amount,
                            payment_method, reference_no, notes, processed_by
                        ) VALUES (?, ?, (SELECT net_salary FROM salary_records WHERE salary_id = ?), ?, ?, ?, ?)";
                        
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param(
                "isisssi",
                $salary_id,
                $payment_data['payment_date'],
                $salary_id, // For amount subquery
                $payment_data['payment_method'],
                $payment_data['reference_no'],
                $payment_data['notes'],
                $payment_data['processed_by']
            );
            
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Update salary record status
            $update_query = "UPDATE salary_records SET
                            payment_status = ?,
                            payment_date = ?,
                            payment_method = ?,
                            reference_no = ?,
                            updated_at = NOW()
                        WHERE salary_id = ?";
                        
            $update_stmt = $conn->prepare($update_query);
            $status = 'paid';
            $update_stmt->bind_param(
                "ssssi",
                $status,
                $payment_data['payment_date'],
                $payment_data['payment_method'],
                $payment_data['reference_no'],
                $salary_id
            );
            
            $update_stmt->execute();
            $update_stmt->close();
            
            $success_count++;
        }
        
        $conn->commit();
        
        return [
            'success_count' => $success_count,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        $conn->rollback();
        
        return [
            'success_count' => 0,
            'errors' => ["Error processing batch payments: " . $e->getMessage()]
        ];
    }
}/**
 * Temporary function to check permissions (always returns true)
 * 
 * @param string $permission The permission to check
 * @return bool Always returns true for now
 */
if (!function_exists('has_permission')) {
    function has_permission($permission) {
        // This will only be used if the auth.php version doesn't exist
        return true;
    }
}