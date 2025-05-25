prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Calculate attendance allowance based on attendance
 */
function calculateAttendanceAllowance($staff_id, $period_start, $period_end) {
    global $conn;
    
    // Get employee salary config
    $config = getEmployeeSalaryConfig($staff_id);
    if (!$config) return 0;
    
    $allowance_rate = $config['attendance_allowance_rate'];
    
    // Count present days
    $query = "SELECT COUNT(*) as present_days 
              FROM attendance_records 
              WHERE staff_id = ? 
              AND attendance_date BETWEEN ? AND ? 
              AND status IN ('present', 'half_day')";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $staff_id, $period_start, $period_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $present_days = $row['present_days'] ?? 0;
    
    // Calculate half days as 0.5
    $half_day_query = "SELECT COUNT(*) as half_days 
                       FROM attendance_records 
                       WHERE staff_id = ? 
                       AND attendance_date BETWEEN ? AND ? 
                       AND status = 'half_day'";
                       
    $half_stmt = $conn->prepare($half_day_query);
    $half_stmt->bind_param("iss", $staff_id, $period_start, $period_end);
    $half_stmt->execute();
    $half_result = $half_stmt->get_result();
    $half_row = $half_result->fetch_assoc();
    
    $half_days = $half_row['half_days'] ?? 0;
    $effective_days = $present_days - ($half_days * 0.5);
    
    return $effective_days * $allowance_rate;
}

/**
 * Get cash settlement adjustments for employee
 */
function getCashSettlementAdjustments($staff_id, $pay_period = null) {
    global $conn;
    
    $query = "SELECT * FROM salary_cash_adjustments 
              WHERE staff_id = ? AND applied_to_salary = 0";
              
    if ($pay_period) {
        $query .= " AND (salary_period IS NULL OR salary_period = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $staff_id, $pay_period);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $staff_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adjustments = [];
    while ($row = $result->fetch_assoc()) {
        $adjustments[] = $row;
    }
    
    return $adjustments;
}

/**
 * Add cash settlement adjustment
 */
function addCashSettlementAdjustment($staff_id, $settlement_date, $type, $amount, $description, $user_id) {
    global $conn;
    
    $query = "INSERT INTO salary_cash_adjustments 
              (staff_id, settlement_date, adjustment_type, amount, description, created_by)
              VALUES (?, ?, ?, ?, ?, ?)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issdsi", $staff_id, $settlement_date, $type, $amount, $description, $user_id);
    
    return $stmt->execute();
}

/**
 * Get employee advance payments
 */
function getEmployeeAdvancePayments($staff_id, $status = 'active') {
    global $conn;
    
    $query = "SELECT * FROM employee_advance_payments 
              WHERE staff_id = ? AND status = ?
              ORDER BY advance_date DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $staff_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $advances = [];
    while ($row = $result->fetch_assoc()) {
        $advances[] = $row;
    }
    
    return $advances;
}

/**
 * Add advance payment
 */
function addAdvancePayment($staff_id, $amount, $deduction_per_month, $reason, $user_id) {
    global $conn;
    
    $query = "INSERT INTO employee_advance_payments 
              (staff_id, advance_date, amount, remaining_amount, deduction_per_month, reason, created_by)
              VALUES (?, CURDATE(), ?, ?, ?, ?, ?)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("idddsi", $staff_id, $amount, $amount, $deduction_per_month, $reason, $user_id);
    
    return $stmt->execute();
}

/**
 * Calculate monthly salary for permanent employee
 */
function calculateMonthlySalaryEnhanced($staff_id, $pay_period, $user_id) {
    global $conn;
    
    // Get employee config
    $config = getEmployeeSalaryConfig($staff_id);
    if (!$config) {
        return "No salary configuration found";
    }
    
    if ($config['employee_type'] !== 'permanent') {
        return "This function is for permanent employees only";
    }
    
    // Calculate period dates
    list($year, $month) = explode('-', $pay_period);
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Basic salary components
    $basic_salary = $config['basic_salary'];
    $transport_allowance = $config['transport_allowance'];
    $meal_allowance = $config['meal_allowance'];
    $housing_allowance = $config['housing_allowance'];
    $other_allowance = $config['other_allowance'];
    
    // Calculate attendance allowance
    $attendance_allowance = calculateAttendanceAllowance($staff_id, $start_date, $end_date);
    
    // Get overtime
    $overtime_query = "SELECT SUM(overtime_hours) as total_hours
                       FROM overtime_records or1
                       JOIN attendance_records ar ON or1.attendance_id = ar.attendance_id
                       WHERE ar.staff_id = ? AND ar.attendance_date BETWEEN ? AND ?
                       AND or1.status = 'approved'";
                       
    $ot_stmt = $conn->prepare($overtime_query);
    $ot_stmt->bind_param("iss", $staff_id, $start_date, $end_date);
    $ot_stmt->execute();
    $ot_result = $ot_stmt->get_result();
    $ot_data = $ot_result->fetch_assoc();
    
    $overtime_hours = $ot_data['total_hours'] ?? 0;
    
    // Calculate overtime amount
    $overtime_amount = 0;
    if ($overtime_hours > 0) {
        if ($config['overtime_calculation_method'] === 'hourly') {
            $hourly_rate = $basic_salary / (8 * 22); // Assuming 8 hrs/day, 22 days/month
            $overtime_amount = $overtime_hours * $hourly_rate * ($config['overtime_rate_regular'] / 100);
        } else {
            $overtime_amount = $overtime_hours * $config['overtime_rate_regular'];
        }
    }
    
    // Calculate gross salary
    $gross_salary = $basic_salary + $transport_allowance + $meal_allowance + 
                    $housing_allowance + $attendance_allowance + $other_allowance + $overtime_amount;
    
    // Calculate statutory deductions
    $epf_employee = ($basic_salary * $config['epf_employee_percent']) / 100;
    $epf_employer = ($basic_salary * $config['epf_employer_percent']) / 100;
    $etf = ($basic_salary * $config['etf_percent']) / 100;
    $paye_tax = ($gross_salary * $config['paye_tax_percent']) / 100;
    
    // Get cash settlement adjustments
    $cash_adjustments = getCashSettlementAdjustments($staff_id, $pay_period);
    $cash_excess = 0;
    $cash_shortage = 0;
    
    foreach ($cash_adjustments as $adj) {
        if ($adj['adjustment_type'] === 'excess') {
            $cash_excess += $adj['amount'];
        } else {
            $cash_shortage += $adj['amount'];
        }
    }
    
    // Get advance payment deductions
    $advances = getEmployeeAdvancePayments($staff_id, 'active');
    $advance_deductions = 0;
    foreach ($advances as $advance) {
        $advance_deductions += min($advance['deduction_per_month'], $advance['remaining_amount']);
    }
    
    // Calculate loan deductions
    $loans = getEmployeeLoans($staff_id, 'active');
    $loan_deductions = 0;
    foreach ($loans as $loan) {
        $loan_deductions += $loan['monthly_deduction'];
    }
    
    // Calculate total deductions
    $total_deductions = $epf_employee + $paye_tax + $cash_shortage + 
                        $advance_deductions + $loan_deductions;
    
    // Calculate net salary (add cash excess)
    $net_salary = $gross_salary - $total_deductions + $cash_excess;
    
    return [
        'staff_id' => $staff_id,
        'employee_type' => 'permanent',
        'pay_period' => $pay_period,
        'basic_salary' => $basic_salary,
        'transport_allowance' => $transport_allowance,
        'meal_allowance' => $meal_allowance,
        'housing_allowance' => $housing_allowance,
        'attendance_allowance' => $attendance_allowance,
        'other_allowance' => $other_allowance,
        'overtime_hours' => $overtime_hours,
        'overtime_amount' => $overtime_amount,
        'gross_salary' => $gross_salary,
        'epf_employee' => $epf_employee,
        'epf_employer' => $epf_employer,
        'etf' => $etf,
        'paye_tax' => $paye_tax,
        'loan_deductions' => $loan_deductions,
        'cash_settlement_excess' => $cash_excess,
        'cash_settlement_shortage' => $cash_shortage,
        'advance_deductions' => $advance_deductions,
        'total_deductions' => $total_deductions,
        'net_salary' => $net_salary,
        'calculated_by' => $user_id
    ];
}

/**
 * Calculate daily salary for casual employee
 */
function calculateDailySalary($staff_id, $work_date, $user_id) {
    global $conn;
    
    // Get employee config
    $config = getEmployeeSalaryConfig($staff_id);
    if (!$config) {
        return "No salary configuration found";
    }
    
    if ($config['employee_type'] !== 'casual') {
        return "This function is for casual employees only";
    }
    
    // Get attendance for the day
    $att_query = "SELECT * FROM attendance_records 
                  WHERE staff_id = ? AND attendance_date = ?";
    $att_stmt = $conn->prepare($att_query);
    $att_stmt->bind_param("is", $staff_id, $work_date);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();
    
    if ($att_result->num_rows === 0) {
        return "No attendance record found for this date";
    }
    
    $attendance = $att_result->fetch_assoc();
    
    // Calculate hours worked
    $hours_worked = $attendance['hours_worked'] ?? 8;
    if ($attendance['status'] === 'half_day') {
        $hours_worked = 4;
    } elseif ($attendance['status'] !== 'present') {
        $hours_worked = 0;
    }
    
    // Calculate basic daily amount
    $daily_rate = $config['daily_rate'];
    $basic_amount = ($hours_worked / 8) * $daily_rate;
    
    // Get overtime
    $ot_query = "SELECT overtime_hours FROM overtime_records or1
                 JOIN attendance_records ar ON or1.attendance_id = ar.attendance_id
                 WHERE ar.staff_id = ? AND ar.attendance_date = ?
                 AND or1.status = 'approved'";
    $ot_stmt = $conn->prepare($ot_query);
    $ot_stmt->bind_param("is", $staff_id, $work_date);
    $ot_stmt->execute();
    $ot_result = $ot_stmt->get_result();
    $ot_data = $ot_result->fetch_assoc();
    
    $overtime_hours = $ot_data['overtime_hours'] ?? 0;
    $overtime_rate = $config['overtime_rate_regular'];
    
    // Calculate overtime amount
    $overtime_amount = 0;
    if ($overtime_hours > 0) {
        if ($config['overtime_calculation_method'] === 'hourly') {
            $hourly_rate = $daily_rate / 8;
            $overtime_amount = $overtime_hours * $hourly_rate * ($overtime_rate / 100);
        } else {
            $overtime_amount = $overtime_hours * $overtime_rate;
        }
    }
    
    // Attendance allowance (if present)
    $attendance_allowance = 0;
    if ($attendance['status'] === 'present') {
        $attendance_allowance = $config['attendance_allowance_rate'];
    } elseif ($attendance['status'] === 'half_day') {
        $attendance_allowance = $config['attendance_allowance_rate'] / 2;
    }
    
    // Calculate gross amount
    $gross_amount = $basic_amount + $overtime_amount + $attendance_allowance;
    
    // Deductions (minimal for casual workers)
    $deductions = 0;
    
    // Net amount
    $net_amount = $gross_amount - $deductions;
    
    return [
        'staff_id' => $staff_id,
        'work_date' => $work_date,
        'daily_rate' => $daily_rate,
        'hours_worked' => $hours_worked,
        'overtime_hours' => $overtime_hours,
        'overtime_rate' => $overtime_rate,
        'attendance_allowance' => $attendance_allowance,
        'other_allowance' => 0,
        'gross_amount' => $gross_amount,
        'deductions' => $deductions,
        'net_amount' => $net_amount
    ];
}

/**
 * Save daily salary record
 */
function saveDailySalaryRecord($salary_data, $user_id) {
    global $conn;
    
    $query = "INSERT INTO daily_salary_records 
              (staff_id, work_date, daily_rate, hours_worked, overtime_hours, 
               overtime_rate, attendance_allowance, other_allowance, gross_amount, 
               deductions, net_amount, created_by)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              daily_rate = VALUES(daily_rate),
              hours_worked = VALUES(hours_worked),
              overtime_hours = VALUES(overtime_hours),
              overtime_rate = VALUES(overtime_rate),
              attendance_allowance = VALUES(attendance_allowance),
              gross_amount = VALUES(gross_amount),
              deductions = VALUES(deductions),
              net_amount = VALUES(net_amount)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isdddddddddi", 
        $salary_data['staff_id'],
        $salary_data['work_date'],
        $salary_data['daily_rate'],
        $salary_data['hours_worked'],
        $salary_data['overtime_hours'],
        $salary_data['overtime_rate'],
        $salary_data['attendance_allowance'],
        $salary_data['other_allowance'],
        $salary_data['gross_amount'],
        $salary_data['deductions'],
        $salary_data['net_amount'],
        $user_id
    );
    
    return $stmt->execute();
}

/**
 * Get daily salary report for a date range
 */
function getDailySalaryReport($start_date, $end_date, $staff_id = null) {
    global $conn;
    
    $query = "SELECT dsr.*, s.first_name, s.last_name, s.staff_code, s.position
              FROM daily_salary_records dsr
              JOIN staff s ON dsr.staff_id = s.staff_id
              WHERE dsr.work_date BETWEEN ? AND ?";
              
    if ($staff_id) {
        $query .= " AND dsr.staff_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $start_date, $end_date, $staff_id);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $query .= " ORDER BY dsr.work_date DESC, s.first_name";
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    return $records;
}

/**
 * Process advance payment deduction
 */
function processAdvanceDeduction($advance_id, $deduction_amount) {
    global $conn;
    
    $query = "UPDATE employee_advance_payments 
              SET remaining_amount = remaining_amount - ?,
                  status = CASE 
                    WHEN remaining_amount - ? <= 0 THEN 'completed'
                    ELSE status
                  END,
                  updated_at = NOW()
              WHERE advance_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ddi", $deduction_amount, $deduction_amount, $advance_id);
    
    return $stmt->execute();
}

/**
 * Mark cash settlement adjustments as applied
 */
function markCashAdjustmentsApplied($staff_id, $pay_period) {
    global $conn;
    
    $query = "UPDATE salary_cash_adjustments 
              SET applied_to_salary = 1, salary_period = ?
              WHERE staff_id = ? AND applied_to_salary = 0";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $pay_period, $staff_id);
    
    return $stmt->execute();
}
?>