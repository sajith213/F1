<?php
/**
 * Salary Calculator
 * 
 * This file handles individual employee salary calculations
 */

// Set page title
$page_title = "Salary Calculator";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>Salary Calculator</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Check permissions
if (!has_permission('calculate_salaries')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Initialize variables
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$pay_period = sprintf('%04d-%02d', $year, $month);

$success_message = '';
$error_message = '';
$calculation_result = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calculate_salary'])) {
        // Get form data
        $staff_id = $_POST['staff_id'];
        $year = $_POST['year'];
        $month = $_POST['month'];
        $pay_period = sprintf('%04d-%02d', $year, $month);
        
        // Get calculation parameters
        $days_worked = $_POST['days_worked'];
        $leave_days = $_POST['leave_days'];
        $absent_days = $_POST['absent_days'];
        $overtime_hours = $_POST['overtime_hours'];
        
        // Additional deductions if provided
        $additional_deductions = isset($_POST['additional_deductions']) ? $_POST['additional_deductions'] : 0;
        $deduction_notes = isset($_POST['deduction_notes']) ? $_POST['deduction_notes'] : '';
        
        // Calculate salary
        $calculation_result = calculateEmployeeSalary(
            $staff_id, 
            $pay_period, 
            $days_worked, 
            $leave_days, 
            $absent_days, 
            $overtime_hours, 
            $additional_deductions
        );
    }
    
    if (isset($_POST['save_calculation'])) {
        // Get form data
        $staff_id = $_POST['staff_id'];
        $year = $_POST['year'];
        $month = $_POST['month'];
        $pay_period = sprintf('%04d-%02d', $year, $month);
        
        // Get salary data from form
        $salary_data = [
            'staff_id' => $staff_id,
            'pay_period' => $pay_period,
            'basic_salary' => $_POST['basic_salary'],
            'transport_allowance' => $_POST['transport_allowance'],
            'meal_allowance' => $_POST['meal_allowance'],
            'housing_allowance' => $_POST['housing_allowance'],
            'other_allowance' => $_POST['other_allowance'],
            'overtime_hours' => $_POST['overtime_hours'],
            'overtime_amount' => $_POST['overtime_amount'],
            'gross_salary' => $_POST['gross_salary'],
            'epf_employee' => $_POST['epf_employee'],
            'epf_employer' => $_POST['epf_employer'],
            'etf' => $_POST['etf'],
            'paye_tax' => $_POST['paye_tax'],
            'loan_deductions' => $_POST['loan_deductions'],
            'other_deductions' => $_POST['other_deductions'],
            'total_deductions' => $_POST['total_deductions'],
            'net_salary' => $_POST['net_salary'],
            'days_worked' => $_POST['days_worked'],
            'leave_days' => $_POST['leave_days'],
            'absent_days' => $_POST['absent_days'],
            'payment_status' => 'pending',
            'calculated_by' => $_SESSION['user_id'],
            'notes' => $_POST['notes']
        ];
        
        // Save salary record
        $result = saveSalaryRecord($salary_data);
        
        if ($result === true) {
            $success_message = "Salary record saved successfully for {$_POST['employee_name']} for " . date('F Y', strtotime($pay_period . '-01'));
            // Reset calculation result to show new calculation form
            $calculation_result = null;
        } else {
            $error_message = $result;
        }
    }
}

// Get all staff for dropdown
$all_staff_query = "SELECT staff_id, first_name, last_name, staff_code FROM staff WHERE status = 'active' ORDER BY first_name, last_name";
$all_staff_result = $conn->query($all_staff_query);
$all_staff = [];
while ($row = $all_staff_result->fetch_assoc()) {
    $all_staff[] = $row;
    if (!$staff_id && empty($all_staff)) {
        $staff_id = $row['staff_id']; // Set first staff as default if none selected
    }
}

// Get employee details if staff_id is set
$employee = null;
if ($staff_id) {
    $employee_query = "SELECT s.*, 
                        (SELECT esi.basic_salary FROM employee_salary_info esi 
                         WHERE esi.staff_id = s.staff_id AND esi.status = 'active' 
                         ORDER BY esi.effective_date DESC LIMIT 1) as basic_salary
                     FROM staff s
                     WHERE s.staff_id = ?";
    $employee_stmt = $conn->prepare($employee_query);
    $employee_stmt->bind_param("i", $staff_id);
    $employee_stmt->execute();
    $employee_result = $employee_stmt->get_result();
    $employee = $employee_result->fetch_assoc();
    $employee_stmt->close();
}

// Check if a salary record already exists for this period
$existing_record = null;
if ($staff_id) {
    $check_query = "SELECT * FROM salary_records WHERE staff_id = ? AND pay_period = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $staff_id, $pay_period);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $existing_record = $check_result->fetch_assoc();
    }
    $check_stmt->close();
}

// Get employee salary info
$salary_info = null;
if ($staff_id) {
    $salary_info = getEmployeeSalaryInfo($staff_id);
}

// Get employee attendance for the period
$attendance = null;
if ($staff_id) {
    // Calculate date range for the month
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $attendance_query = "SELECT 
                         SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                         SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
                         SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
                     FROM attendance_records 
                     WHERE staff_id = ? AND attendance_date BETWEEN ? AND ?";
    $attendance_stmt = $conn->prepare($attendance_query);
    $attendance_stmt->bind_param("iss", $staff_id, $start_date, $end_date);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    $attendance = $attendance_result->fetch_assoc();
    $attendance_stmt->close();
    
    // Default to zeros if no attendance found
    if (!$attendance) {
        $attendance = [
            'present_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0
        ];
    }
    
    // Get overtime hours
    $overtime_query = "SELECT SUM(overtime_hours) as total_ot_hours
                      FROM overtime_records o
                      JOIN attendance_records a ON o.attendance_id = a.attendance_id
                      WHERE a.staff_id = ? AND a.attendance_date BETWEEN ? AND ? 
                      AND o.status = 'approved'";
    $overtime_stmt = $conn->prepare($overtime_query);
    $overtime_stmt->bind_param("iss", $staff_id, $start_date, $end_date);
    $overtime_stmt->execute();
    $overtime_result = $overtime_stmt->get_result();
    $overtime_data = $overtime_result->fetch_assoc();
    $attendance['overtime_hours'] = $overtime_data['total_ot_hours'] ?? 0;
    $overtime_stmt->close();
}

// Get active loans for this employee
$active_loans = [];
if ($staff_id) {
    $loans_query = "SELECT * FROM employee_loans 
                   WHERE staff_id = ? AND status = 'active' 
                   AND remaining_amount > 0";
    $loans_stmt = $conn->prepare($loans_query);
    $loans_stmt->bind_param("i", $staff_id);
    $loans_stmt->execute();
    $loans_result = $loans_stmt->get_result();
    while ($loan = $loans_result->fetch_assoc()) {
        $active_loans[] = $loan;
    }
    $loans_stmt->close();
}

// Calculate total monthly loan deductions
$total_loan_deductions = 0;
foreach ($active_loans as $loan) {
    $total_loan_deductions += $loan['monthly_deduction'];
}

// Get number of working days in month
$working_days = getWorkingDaysInMonth($year, $month);

// Format month name
$month_name = date('F', mktime(0, 0, 0, $month, 10));
?>

<!-- Main content -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Salary Calculator</h2>
    </div>
    
    <div class="p-4">
        <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?= $success_message ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?= $error_message ?></p>
        </div>
        <?php endif; ?>

        <!-- Employee and Month Selection Form -->
        <?php if (!$calculation_result): ?>
        <form method="post" action="salary_calculator.php" id="calculate-form">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Employee Selection -->
                <div>
                    <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Select Employee</label>
                    <select id="staff_id" name="staff_id" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($all_staff as $staff): ?>
                            <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (' . $staff['staff_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Month Selection -->
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select id="month" name="month" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Year Selection -->
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select id="year" name="year" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <?php if ($existing_record): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            A salary record already exists for this employee in <?= $month_name ?> <?= $year ?>.
                        </p>
                        <p class="text-sm text-yellow-700 mt-2">
                            <a href="payslip.php?salary_id=<?= $existing_record['salary_id'] ?>" class="font-medium underline">
                                View existing record
                            </a> or 
                            <a href="salary_calculator.php?staff_id=<?= $staff_id ?>&year=<?= $year ?>&month=<?= $month ?>&force=1" class="font-medium underline">
                                recalculate anyway
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            <?php elseif ($staff_id && $salary_info): ?>
            <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Employee Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-700 mb-2">Employee Information</h3>
                    <?php if ($employee): ?>
                    <table class="w-full text-sm">
                        <tr>
                            <td class="py-1 text-gray-600">Name:</td>
                            <td class="py-1 font-medium"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Employee ID:</td>
                            <td class="py-1 font-medium"><?= htmlspecialchars($employee['staff_code']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Position:</td>
                            <td class="py-1 font-medium"><?= htmlspecialchars($employee['position']) ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Department:</td>
                            <td class="py-1 font-medium"><?= htmlspecialchars($employee['department'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Basic Salary:</td>
                            <td class="py-1 font-medium">Rs. <?= number_format($salary_info['basic_salary'], 2) ?></td>
                        </tr>
                    </table>
                    <?php else: ?>
                    <p class="text-gray-500">Employee information not found.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Salary Period Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-700 mb-2">Pay Period Information</h3>
                    <table class="w-full text-sm">
                        <tr>
                            <td class="py-1 text-gray-600">Pay Period:</td>
                            <td class="py-1 font-medium"><?= $month_name ?> <?= $year ?></td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Working Days in Month:</td>
                            <td class="py-1 font-medium"><?= $working_days ?> days</td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Recorded Present Days:</td>
                            <td class="py-1 font-medium"><?= $attendance['present_days'] ?? 0 ?> days</td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Recorded Leave Days:</td>
                            <td class="py-1 font-medium"><?= $attendance['leave_days'] ?? 0 ?> days</td>
                        </tr>
                        <tr>
                            <td class="py-1 text-gray-600">Active Loans:</td>
                            <td class="py-1 font-medium">
                                <?php if (count($active_loans) > 0): ?>
                                    <?= count($active_loans) ?> (Rs. <?= number_format($total_loan_deductions, 2) ?>/month)
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="mb-6 border-t border-gray-200 pt-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Attendance & Overtime Details</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Adjust the values below based on actual attendance and overtime for this pay period.
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="days_worked" class="block text-sm font-medium text-gray-700 mb-1">Days Worked</label>
                        <input type="number" id="days_worked" name="days_worked" value="<?= $attendance['present_days'] ?? $working_days ?>" 
                               min="0" max="31" step="1" 
                               class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="leave_days" class="block text-sm font-medium text-gray-700 mb-1">Leave Days</label>
                        <input type="number" id="leave_days" name="leave_days" value="<?= $attendance['leave_days'] ?? 0 ?>" 
                               min="0" max="31" step="1" 
                               class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="absent_days" class="block text-sm font-medium text-gray-700 mb-1">Absent Days</label>
                        <input type="number" id="absent_days" name="absent_days" value="<?= $attendance['absent_days'] ?? 0 ?>" 
                               min="0" max="31" step="1" 
                               class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="overtime_hours" class="block text-sm font-medium text-gray-700 mb-1">Overtime Hours</label>
                        <input type="number" id="overtime_hours" name="overtime_hours" value="<?= $attendance['overtime_hours'] ?? 0 ?>" 
                               min="0" step="0.5" 
                               class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Additional Deductions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-1">
                        <label for="additional_deductions" class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs.)</label>
                        <input type="number" id="additional_deductions" name="additional_deductions" value="0" 
                               min="0" step="0.01" 
                               class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="deduction_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <input type="text" id="deduction_notes" name="deduction_notes" 
                               placeholder="Reason for additional deduction" 
                               class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="calculate_salary" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition">
                    <i class="fas fa-calculator mr-2"></i> Calculate Salary
                </button>
            </div>
            <?php else: ?>
            <div class="text-center py-6 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-blue-700">Please select an employee to calculate salary.</p>
                <?php if (!$salary_info && $staff_id): ?>
                <p class="text-sm text-blue-600 mt-2">No salary information found for this employee. Please set up their salary first.</p>
                <a href="employee_salary.php?staff_id=<?= $staff_id ?>" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    <i class="fas fa-cog mr-2"></i> Set Up Salary
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
        
        <?php else: ?>
        <!-- Salary Calculation Result -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-800">
                    Salary Calculation for <?= htmlspecialchars($calculation_result['employee_name']) ?> - <?= $month_name ?> <?= $year ?>
                </h3>
                <a href="salary_calculator.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Calculator
                </a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Earnings -->
                <div class="bg-white border rounded-lg shadow-sm p-4">
                    <h4 class="text-md font-medium text-gray-700 mb-2 border-b pb-2">Earnings</h4>
                    <table class="w-full text-sm">
                        <tr>
                            <td class="py-2 text-gray-600">Basic Salary:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['basic_salary'], 2) ?></td>
                        </tr>
                        <?php if ($calculation_result['transport_allowance'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Transport Allowance:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['transport_allowance'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculation_result['meal_allowance'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Meal Allowance:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['meal_allowance'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculation_result['housing_allowance'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Housing Allowance:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['housing_allowance'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculation_result['other_allowance'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Other Allowances:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['other_allowance'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculation_result['overtime_amount'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Overtime (<?= $calculation_result['overtime_hours'] ?> hours):</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['overtime_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-t border-gray-200">
                            <td class="py-2 font-medium">Gross Earnings:</td>
                            <td class="py-2 text-right font-bold">Rs. <?= number_format($calculation_result['gross_salary'], 2) ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Deductions -->
                <div class="bg-white border rounded-lg shadow-sm p-4">
                    <h4 class="text-md font-medium text-gray-700 mb-2 border-b pb-2">Deductions</h4>
                    <table class="w-full text-sm">
                        <tr>
                            <td class="py-2 text-gray-600">EPF Employee (<?= $calculation_result['epf_employee_rate'] ?>%):</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['epf_employee'], 2) ?></td>
                        </tr>
                        <?php if ($calculation_result['paye_tax'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">PAYE Tax:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['paye_tax'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculation_result['loan_deductions'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Loan Repayments:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['loan_deductions'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($calculation_result['other_deductions'] > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Other Deductions:</td>
                            <td class="py-2 text-right font-medium">Rs. <?= number_format($calculation_result['other_deductions'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-t border-gray-200">
                            <td class="py-2 font-medium">Total Deductions:</td>
                            <td class="py-2 text-right font-bold">Rs. <?= number_format($calculation_result['total_deductions'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Net Salary -->
                <div class="md:col-span-1 bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                    <h4 class="text-md font-medium text-blue-700 mb-2">Net Salary</h4>
                    <div class="text-2xl font-bold text-blue-800">Rs. <?= number_format($calculation_result['net_salary'], 2) ?></div>
                </div>
                
                <!-- Employer Contributions -->
                <div class="md:col-span-2 bg-white border rounded-lg shadow-sm p-4">
                    <h4 class="text-md font-medium text-gray-700 mb-2 border-b pb-2">Employer Contributions</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600 text-sm">EPF Employer (<?= $calculation_result['epf_employer_rate'] ?>%):</p>
                            <p class="font-medium">Rs. <?= number_format($calculation_result['epf_employer'], 2) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">ETF (<?= $calculation_result['etf_rate'] ?>%):</p>
                            <p class="font-medium">Rs. <?= number_format($calculation_result['etf'], 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Save Calculation Form -->
            <form method="post" action="salary_calculator.php">
                <input type="hidden" name="staff_id" value="<?= $calculation_result['staff_id'] ?>">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="employee_name" value="<?= htmlspecialchars($calculation_result['employee_name']) ?>">
                
                <!-- Earnings -->
                <input type="hidden" name="basic_salary" value="<?= $calculation_result['basic_salary'] ?>">
                <input type="hidden" name="transport_allowance" value="<?= $calculation_result['transport_allowance'] ?>">
                <input type="hidden" name="meal_allowance" value="<?= $calculation_result['meal_allowance'] ?>">
                <input type="hidden" name="housing_allowance" value="<?= $calculation_result['housing_allowance'] ?>">
                <input type="hidden" name="other_allowance" value="<?= $calculation_result['other_allowance'] ?>">
                <input type="hidden" name="overtime_hours" value="<?= $calculation_result['overtime_hours'] ?>">
                <input type="hidden" name="overtime_amount" value="<?= $calculation_result['overtime_amount'] ?>">
                <input type="hidden" name="gross_salary" value="<?= $calculation_result['gross_salary'] ?>">
                
                <!-- Deductions -->
                <input type="hidden" name="epf_employee" value="<?= $calculation_result['epf_employee'] ?>">
                <input type="hidden" name="epf_employer" value="<?= $calculation_result['epf_employer'] ?>">
                <input type="hidden" name="etf" value="<?= $calculation_result['etf'] ?>">
                <input type="hidden" name="paye_tax" value="<?= $calculation_result['paye_tax'] ?>">
                <input type="hidden" name="loan_deductions" value="<?= $calculation_result['loan_deductions'] ?>">
                <input type="hidden" name="other_deductions" value="<?= $calculation_result['other_deductions'] ?>">
                <input type="hidden" name="total_deductions" value="<?= $calculation_result['total_deductions'] ?>">
                <input type="hidden" name="net_salary" value="<?= $calculation_result['net_salary'] ?>">
                
                <!-- Attendance -->
                <input type="hidden" name="days_worked" value="<?= $calculation_result['days_worked'] ?>">
                <input type="hidden" name="leave_days" value="<?= $calculation_result['leave_days'] ?>">
                <input type="hidden" name="absent_days" value="<?= $calculation_result['absent_days'] ?>">
                
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="form-textarea w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                            placeholder="Add any notes about this salary calculation"><?= isset($calculation_result['notes']) ? htmlspecialchars($calculation_result['notes']) : '' ?></textarea>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <a href="salary_calculator.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-6 rounded-lg transition">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <button type="submit" name="save_calculation" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-lg transition">
                        <i class="fas fa-save mr-2"></i> Save Salary Record
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for calculations -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // When selecting an employee or changing month/year, redirect to the calculator with the new parameters
    const staffSelect = document.getElementById('staff_id');
    const monthSelect = document.getElementById('month');
    const yearSelect = document.getElementById('year');
    
    if (staffSelect) {
        staffSelect.addEventListener('change', function() {
            if (this.value) {
                const month = monthSelect.value;
                const year = yearSelect.value;
                window.location.href = `salary_calculator.php?staff_id=${this.value}&year=${year}&month=${month}`;
            }
        });
    }
    
    // Function to update month/year without changing employee
    const updatePeriod = function() {
        const staffId = staffSelect.value;
        if (staffId) {
            const month = monthSelect.value;
            const year = yearSelect.value;
            window.location.href = `salary_calculator.php?staff_id=${staffId}&year=${year}&month=${month}`;
        }
    };
    
    if (monthSelect) {
        monthSelect.addEventListener('change', updatePeriod);
    }
    
    if (yearSelect) {
        yearSelect.addEventListener('change', updatePeriod);
    }
});
</script>

<?php
// Helper function to get number of working days in a month
// This is a simplified version, ideally you would account for holidays
function getWorkingDaysInMonth($year, $month) {
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day');
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $workdays = 0;
    foreach ($period as $day) {
        $dayOfWeek = $day->format('N');
        if ($dayOfWeek < 6) { // 1 (Monday) to 5 (Friday) are workdays, adjust as needed
            $workdays++;
        }
    }
    
    return $workdays;
}

// Include footer
include_once('../../includes/footer.php');
?>