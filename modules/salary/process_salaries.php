<?php
/**
 * Process Salaries
 * 
 * This file handles batch processing of employee salaries
 */

// Set page title
$page_title = "Process Salaries";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>Process Salaries</span>';

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
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$pay_period = sprintf('%04d-%02d', $year, $month);

$success_message = '';
$error_message = '';
$processing_results = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_salaries'])) {
        // Get form data
        $year = $_POST['year'];
        $month = $_POST['month'];
        $pay_period = sprintf('%04d-%02d', $year, $month);
        $staff_ids = $_POST['staff_ids'] ?? [];
        
        // Validate data
        if (empty($staff_ids)) {
            $error_message = "No employees selected for salary processing.";
        } else {
            // Process salaries for selected employees
            $processing_results = [];
            $successful_count = 0;
            
            foreach ($staff_ids as $staff_id) {
                // Get attendance data for this employee
                $attendance = getEmployeeAttendanceForPeriod($staff_id, $year, $month);
                
                // Calculate salary
                $result = calculateEmployeeSalary(
                    $staff_id, 
                    $pay_period, 
                    $attendance['present_days'] ?? 0, 
                    $attendance['leave_days'] ?? 0, 
                    $attendance['absent_days'] ?? 0, 
                    $attendance['overtime_hours'] ?? 0
                );
                
                if (isset($result['error'])) {
                    $processing_results[] = [
                        'employee_name' => getEmployeeName($staff_id),
                        'staff_id' => $staff_id,
                        'status' => 'error',
                        'message' => $result['error']
                    ];
                } else {
                    // Save salary record
                    $salary_data = [
                        'staff_id' => $staff_id,
                        'pay_period' => $pay_period,
                        'basic_salary' => $result['basic_salary'],
                        'transport_allowance' => $result['transport_allowance'],
                        'meal_allowance' => $result['meal_allowance'],
                        'housing_allowance' => $result['housing_allowance'],
                        'other_allowance' => $result['other_allowance'],
                        'overtime_hours' => $result['overtime_hours'],
                        'overtime_amount' => $result['overtime_amount'],
                        'gross_salary' => $result['gross_salary'],
                        'epf_employee' => $result['epf_employee'],
                        'epf_employer' => $result['epf_employer'],
                        'etf' => $result['etf'],
                        'paye_tax' => $result['paye_tax'],
                        'loan_deductions' => $result['loan_deductions'],
                        'other_deductions' => $result['other_deductions'],
                        'total_deductions' => $result['total_deductions'],
                        'net_salary' => $result['net_salary'],
                        'days_worked' => $result['days_worked'],
                        'leave_days' => $result['leave_days'],
                        'absent_days' => $result['absent_days'],
                        'payment_status' => 'pending',
                        'calculated_by' => $_SESSION['user_id'],
                        'notes' => "Batch processed on " . date('Y-m-d H:i:s')
                    ];
                    
                    $save_result = saveSalaryRecord($salary_data);
                    
                    if ($save_result === true) {
                        $processing_results[] = [
                            'employee_name' => $result['employee_name'],
                            'staff_id' => $staff_id,
                            'status' => 'success',
                            'net_salary' => $result['net_salary'],
                            'message' => 'Salary processed successfully'
                        ];
                        $successful_count++;
                    } else {
                        $processing_results[] = [
                            'employee_name' => $result['employee_name'],
                            'staff_id' => $staff_id,
                            'status' => 'error',
                            'message' => $save_result
                        ];
                    }
                }
            }
            
            if ($successful_count > 0) {
                $success_message = "$successful_count employee salaries processed successfully for " . date('F Y', strtotime($pay_period . '-01')) . ".";
            }
        }
    }
}

// Get all departments for filter
$departments_query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Get employees for the current selection
$employees = [];

$employees_query = "SELECT s.staff_id, s.first_name, s.last_name, s.staff_code, s.department, s.position,
                    (SELECT esi.basic_salary FROM employee_salary_info esi 
                     WHERE esi.staff_id = s.staff_id AND esi.status = 'active' 
                     ORDER BY esi.effective_date DESC LIMIT 1) as basic_salary,
                    (SELECT COUNT(*) FROM salary_records sr 
                     WHERE sr.staff_id = s.staff_id AND sr.pay_period = ?) as has_salary
                   FROM staff s
                   WHERE s.status = 'active'";

// Add department filter if selected
if (!empty($department)) {
    $employees_query .= " AND s.department = ?";
    $stmt = $conn->prepare($employees_query);
    $stmt->bind_param("ss", $pay_period, $department);
} else {
    $stmt = $conn->prepare($employees_query);
    $stmt->bind_param("s", $pay_period);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$stmt->close();

// Get salary summary for the period
$summary_query = "SELECT COUNT(*) as total_processed, 
                 SUM(gross_salary) as total_gross, 
                 SUM(net_salary) as total_net,
                 SUM(epf_employee) as total_epf_employee,
                 SUM(epf_employer) as total_epf_employer,
                 SUM(etf) as total_etf
                 FROM salary_records 
                 WHERE pay_period = ?";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("s", $pay_period);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();
$summary_stmt->close();

// Format month name
$month_name = date('F', mktime(0, 0, 0, $month, 10));
?>

<!-- Main Content -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Process Salaries</h2>
        <div>
            <a href="view_salaries.php?year=<?= $year ?>&month=<?= $month ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-eye mr-2"></i> View Processed Salaries
            </a>
        </div>
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
        
        <!-- Period Selection and Filters -->
        <div class="mb-6">
            <form method="get" action="process_salaries.php" id="filter-form" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <select id="month" name="month" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select id="year" name="year" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="this.form.submit()">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select id="department" name="department" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= $department == $dept ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Processing Summary -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="text-md font-medium text-blue-700 mb-2">Processing Summary - <?= $month_name ?> <?= $year ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div>
                    <p class="text-sm text-gray-600">Processed</p>
                    <p class="text-lg font-bold text-blue-800"><?= $summary['total_processed'] ?? 0 ?> / <?= count($employees) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Gross</p>
                    <p class="text-lg font-bold text-blue-800">Rs. <?= number_format($summary['total_gross'] ?? 0, 2) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total EPF/ETF</p>
                    <p class="text-lg font-bold text-blue-800">Rs. <?= number_format(($summary['total_epf_employee'] ?? 0) + ($summary['total_epf_employer'] ?? 0) + ($summary['total_etf'] ?? 0), 2) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Net</p>
                    <p class="text-lg font-bold text-blue-800">Rs. <?= number_format($summary['total_net'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        
        <?php if ($processing_results): ?>
        <!-- Processing Results -->
        <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Processing Results</h3>
            <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($processing_results as $result): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($result['employee_name']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($result['status'] === 'success'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Success
                                </span>
                                <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Error
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (isset($result['net_salary'])): ?>
                                Rs. <?= number_format($result['net_salary'], 2) ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($result['message']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="flex justify-between items-center mt-6">
            <a href="process_salaries.php?year=<?= $year ?>&month=<?= $month ?>&department=<?= urlencode($department) ?>" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-sync-alt mr-1"></i> Refresh Employee List
            </a>
            
            <a href="view_salaries.php?year=<?= $year ?>&month=<?= $month ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-eye mr-2"></i> View All Processed Salaries
            </a>
        </div>
        <?php else: ?>
        
        <!-- Employee Selection Form -->
        <form method="post" action="process_salaries.php" id="process-form">
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            
            <div class="mb-4 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-800">Employees for <?= $month_name ?> <?= $year ?></h3>
                <div class="flex items-center">
                    <label class="inline-flex items-center mr-4">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Select All</span>
                    </label>
                    <button type="submit" name="process_salaries" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition" id="process-button" disabled>
                        <i class="fas fa-cogs mr-2"></i> Process Selected
                    </button>
                </div>
            </div>
            
            <?php if (empty($employees)): ?>
            <div class="text-center py-6 bg-gray-50 rounded-lg border border-gray-200">
                <p class="text-gray-700">No employees found in the system<?= $department ? " for department: $department" : "" ?>.</p>
                <p class="text-sm text-gray-600 mt-2">Please add employees and set up their salaries first.</p>
                <a href="../staff_management/index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    <i class="fas fa-user-plus mr-2"></i> Manage Staff
                </a>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto border rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Select</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basic Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" name="staff_ids[]" value="<?= $employee['staff_id'] ?>" 
                                       class="employee-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                       <?= $employee['has_salary'] > 0 ? 'disabled' : '' ?>>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                </div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($employee['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($employee['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($employee['department'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($employee['basic_salary']): ?>
                                Rs. <?= number_format($employee['basic_salary'], 2) ?>
                                <?php else: ?>
                                <span class="text-red-500">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($employee['has_salary'] > 0): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Processed
                                </span>
                                <?php elseif (!$employee['basic_salary']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    No Salary Info
                                </span>
                                <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($employee['has_salary'] > 0): ?>
                                <a href="view_salaries.php?staff_id=<?= $employee['staff_id'] ?>&year=<?= $year ?>&month=<?= $month ?>" 
                                   class="text-blue-600 hover:text-blue-900" title="View Salary Record">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php else: ?>
                                <a href="salary_calculator.php?staff_id=<?= $employee['staff_id'] ?>&year=<?= $year ?>&month=<?= $month ?>" 
                                   class="text-blue-600 hover:text-blue-900" title="Calculate Individually">
                                    <i class="fas fa-calculator"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for form handling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox:not([disabled])');
    const processButton = document.getElementById('process-button');
    
    // Function to update process button state
    function updateProcessButton() {
        const checkedBoxes = document.querySelectorAll('.employee-checkbox:checked').length;
        processButton.disabled = checkedBoxes === 0;
    }
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            employeeCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateProcessButton();
        });
    }
    
    // Individual checkbox change
    employeeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateProcessButton();
            
            // Update select all checkbox state
            const allChecked = document.querySelectorAll('.employee-checkbox:not([disabled])').length === 
                               document.querySelectorAll('.employee-checkbox:checked').length;
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
    
    // Initial button state
    updateProcessButton();
});
</script>

<?php
// Function to get employee name from ID
function getEmployeeName($staff_id) {
    global $conn;
    $query = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM staff WHERE staff_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['full_name'] : "Unknown Employee";
}

// Function to get employee attendance for a period
function getEmployeeAttendanceForPeriod($staff_id, $year, $month) {
    global $conn;
    
    // Calculate date range for the month
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $attendance = [
        'present_days' => 0,
        'leave_days' => 0,
        'absent_days' => 0,
        'overtime_hours' => 0
    ];
    
    // Get attendance records
    $query = "SELECT 
              SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
              SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
              SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
           FROM attendance_records 
           WHERE staff_id = ? AND attendance_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $staff_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $attendance['present_days'] = $row['present_days'] ?? 0;
        $attendance['leave_days'] = $row['leave_days'] ?? 0;
        $attendance['absent_days'] = $row['absent_days'] ?? 0;
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
    $overtime_row = $overtime_result->fetch_assoc();
    $overtime_stmt->close();
    
    $attendance['overtime_hours'] = $overtime_row['total_ot_hours'] ?? 0;
    
    // If no present days are recorded but we have worked days, use working days in month
    if ($attendance['present_days'] == 0 && $attendance['leave_days'] == 0 && $attendance['absent_days'] == 0) {
        $attendance['present_days'] = getWorkingDaysInMonth($year, $month);
    }
    
    return $attendance;
}

// Helper function to get number of working days in a month
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