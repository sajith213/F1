<?php
/**
 * Employee Payslip
 *
 * This file generates and displays employee payslips
 */

// Set page title
$page_title = "Employee Payslip";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>Payslip</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
// This line includes functions.php, which correctly contains the getWords() function
require_once('functions.php');

// Check permissions
if (!has_permission('view_salary_reports')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Get parameters
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$salary_id = isset($_GET['salary_id']) ? intval($_GET['salary_id']) : null;

// Get company information from settings
$company_name = get_setting('company_name', 'Company Name');
$company_address = get_setting('company_address', 'Company Address');
$company_phone = get_setting('company_phone', 'Phone Number');
$company_email = get_setting('company_email', 'Email');

// Get employee details
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

// Get salary record
$salary_record = null;
if ($salary_id) {
    // Get specific salary record by ID
    $salary_query = "SELECT * FROM salary_records WHERE salary_id = ?";
    $salary_stmt = $conn->prepare($salary_query);
    $salary_stmt->bind_param("i", $salary_id);
    $salary_stmt->execute();
    $salary_result = $salary_stmt->get_result();
    $salary_record = $salary_result->fetch_assoc();
    $salary_stmt->close();

    // Extract year and month from pay period
    if ($salary_record) {
        list($year, $month) = explode('-', $salary_record['pay_period']);
        // Make sure staff_id matches the salary record if it wasn't passed initially
        if (!$staff_id && isset($salary_record['staff_id'])) {
             $staff_id = $salary_record['staff_id'];
             // Re-fetch employee details if staff_id was derived from salary_id
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
    }
} elseif ($staff_id && $year && $month) {
    // Get salary record by staff, year, month
    $pay_period = sprintf('%04d-%02d', $year, $month);
    $salary_query = "SELECT * FROM salary_records WHERE staff_id = ? AND pay_period = ?";
    $salary_stmt = $conn->prepare($salary_query);
    $salary_stmt->bind_param("is", $staff_id, $pay_period);
    $salary_stmt->execute();
    $salary_result = $salary_stmt->get_result();
    $salary_record = $salary_result->fetch_assoc();
    $salary_stmt->close();
     // Set salary_id if found
    if ($salary_record) {
        $salary_id = $salary_record['salary_id'];
    }
}


// Get all salary records for this employee for the dropdown (only if staff_id is known)
$all_salary_records = [];
if ($staff_id) {
    $all_records_query = "SELECT salary_id, pay_period, payment_status, payment_date
                        FROM salary_records
                        WHERE staff_id = ?
                        ORDER BY pay_period DESC";
    $all_records_stmt = $conn->prepare($all_records_query);
    $all_records_stmt->bind_param("i", $staff_id);
    $all_records_stmt->execute();
    $all_records_result = $all_records_stmt->get_result();
    while ($record = $all_records_result->fetch_assoc()) {
        $all_salary_records[] = $record;
    }
    $all_records_stmt->close();
}

// Current month and year names
$current_month_name = date('F', mktime(0, 0, 0, $month, 10));
$month_year = $current_month_name . ' ' . $year;

// Calculate year-to-date (YTD) totals
$ytd_totals = [
    'gross' => 0,
    'epf_employee' => 0,
    'epf_employer' => 0,
    'etf' => 0,
    'net' => 0
];

if ($staff_id) {
    $ytd_query = "SELECT SUM(gross_salary) as ytd_gross,
                    SUM(epf_employee) as ytd_epf_employee,
                    SUM(epf_employer) as ytd_epf_employer,
                    SUM(etf) as ytd_etf,
                    SUM(net_salary) as ytd_net
                FROM salary_records
                WHERE staff_id = ? AND pay_period LIKE ?";
    $ytd_stmt = $conn->prepare($ytd_query);
    $ytd_year = $year . '-%'; // Corrected LIKE pattern
    $ytd_stmt->bind_param("is", $staff_id, $ytd_year);
    $ytd_stmt->execute();
    $ytd_result = $ytd_stmt->get_result();
    $ytd_data = $ytd_result->fetch_assoc();

    if ($ytd_data) {
        $ytd_totals['gross'] = $ytd_data['ytd_gross'] ?: 0;
        $ytd_totals['epf_employee'] = $ytd_data['ytd_epf_employee'] ?: 0;
        $ytd_totals['epf_employer'] = $ytd_data['ytd_epf_employer'] ?: 0;
        $ytd_totals['etf'] = $ytd_data['ytd_etf'] ?: 0;
        $ytd_totals['net'] = $ytd_data['ytd_net'] ?: 0;
    }
    $ytd_stmt->close();
}
?>

<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center print:hidden"> <h2 class="text-lg font-semibold text-gray-800">Employee Payslip</h2>
        <div>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition mr-2">
                <i class="fas fa-print mr-2"></i> Print Payslip
            </button>
            <a href="print_payslip.php?salary_id=<?= $salary_id ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition <?= $salary_id ? '' : 'hidden' ?>"> <i class="fas fa-download mr-2"></i> Download PDF
            </a>
        </div>
    </div>

    <div class="p-4">
        <?php if (!$staff_id): // Check if a staff member is selected ?>
            <div class="text-center py-6 print:hidden"> <label for="employee_select_init" class="block text-sm font-medium text-gray-700 mb-1">Select Employee to View Payslip</label>
                 <select id="employee_select_init" class="form-select w-full max-w-xs mx-auto rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="changeEmployee(this.value)">
                     <option value="">-- Select Employee --</option>
                     <?php
                     $all_staff_query = "SELECT staff_id, first_name, last_name, staff_code FROM staff WHERE status = 'active' ORDER BY first_name, last_name";
                     $all_staff_result = $conn->query($all_staff_query);
                     while ($row = $all_staff_result->fetch_assoc()):
                     ?>
                         <option value="<?= $row['staff_id'] ?>">
                             <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['staff_code'] . ')') ?>
                         </option>
                     <?php endwhile; ?>
                 </select>
                 <p class="mt-4 text-gray-500">Or go back to the Salary Dashboard.</p>
                <a href="index.php" class="mt-2 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i> Return to Salary Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4 print:hidden">
                <div>
                    <label for="employee_select" class="block text-sm font-medium text-gray-700 mb-1">Selected Employee</label>
                    <select id="employee_select" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="changeEmployee(this.value)">
                        <option value="">Select Employee</option>
                        <?php
                        // Re-run query to ensure the selected one is included
                        $all_staff_result = $conn->query($all_staff_query);
                        while ($row = $all_staff_result->fetch_assoc()):
                        ?>
                            <option value="<?= $row['staff_id'] ?>" <?= $staff_id == $row['staff_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['staff_code'] . ')') ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <?php if (count($all_salary_records) > 0): ?>
                <div>
                    <label for="payslip_select" class="block text-sm font-medium text-gray-700 mb-1">Select Payslip Period</label>
                    <select id="payslip_select" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="changePayslip(this.value)">
                        <option value="">-- Select Period --</option> <?php foreach ($all_salary_records as $record): ?>
                            <?php
                            // Ensure pay_period is not null or empty before formatting
                            $period_display = 'Invalid Period';
                            if (!empty($record['pay_period'])) {
                                $period_date = DateTime::createFromFormat('Y-m', $record['pay_period']);
                                if ($period_date) {
                                    $period_display = $period_date->format('F Y');
                                } else {
                                    $period_display = $record['pay_period']; // Fallback to original if format fails
                                }
                            }

                            $status = '';
                            if ($record['payment_status'] == 'paid') {
                                $status = ' (Paid)';
                            } elseif ($record['payment_status'] == 'pending') {
                                $status = ' (Pending)';
                            }
                            ?>
                            <option value="<?= $record['salary_id'] ?>" <?= $salary_id == $record['salary_id'] ? 'selected' : '' ?>>
                                <?= $period_display . $status ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                 <div>
                     <p class="text-sm text-gray-500 mt-6">No payslip history found for this employee.</p>
                 </div>
                <?php endif; ?>
            </div>

            <?php if (!$salary_record): ?>
                <div class="text-center py-6 bg-yellow-50 rounded-lg border border-yellow-200 mb-6">
                     <?php if(count($all_salary_records) > 0): ?>
                        <p class="text-yellow-700">Please select a payslip period from the dropdown above.</p>
                     <?php else: ?>
                        <p class="text-yellow-700">No salary record found for this employee.</p>
                        <p class="text-sm text-yellow-600 mt-2">To generate a salary slip, please process salaries first.</p>
                        <a href="salary_calculator.php?staff_id=<?= $staff_id ?>" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition print:hidden">
                            <i class="fas fa-calculator mr-2"></i> Calculate Salary
                        </a>
                     <?php endif; ?>
                </div>
            <?php else: ?>
                <div id="payslip" class="border rounded-lg mb-6 printable-area"> <div class="p-6 border-b border-gray-200 text-center">
                        <h2 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($company_name) ?></h2>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($company_address) ?></p>
                        <p class="text-sm text-gray-600">Phone: <?= htmlspecialchars($company_phone) ?> | Email: <?= htmlspecialchars($company_email) ?></p>
                        <h3 class="text-lg font-semibold text-blue-700 mt-4">PAYSLIP FOR <?= strtoupper($month_year) ?></h3>
                    </div>

                    <div class="p-6 border-b border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Employee Details</h4>
                            <table class="w-full text-sm">
                                <tr>
                                    <td class="py-1 text-gray-600 w-1/3">Name:</td>
                                    <td class="py-1 font-medium">
                                        <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                    </td>
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
                            </table>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Payslip Details</h4>
                            <table class="w-full text-sm">
                                <tr>
                                    <td class="py-1 text-gray-600 w-1/3">Pay Period:</td>
                                    <td class="py-1 font-medium"><?= $month_year ?></td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-gray-600">Payment Date:</td>
                                    <td class="py-1 font-medium">
                                        <?= $salary_record['payment_date'] ? date('d M, Y', strtotime($salary_record['payment_date'])) : 'Pending' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-gray-600">Payment Method:</td>
                                    <td class="py-1 font-medium">
                                        <?= $salary_record['payment_method'] ? ucfirst(htmlspecialchars($salary_record['payment_method'])) : 'Pending' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-gray-600">Reference:</td>
                                    <td class="py-1 font-medium"><?= htmlspecialchars($salary_record['reference_no'] ?? '-') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Attendance Summary</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <div class="p-2 bg-blue-50 rounded-lg">
                                <div class="text-xs text-gray-600">Days Worked</div>
                                <div class="text-lg font-bold text-blue-700"><?= $salary_record['days_worked'] ?? 0 ?></div>
                            </div>
                            <div class="p-2 bg-green-50 rounded-lg">
                                <div class="text-xs text-gray-600">Leave Days</div>
                                <div class="text-lg font-bold text-green-700"><?= $salary_record['leave_days'] ?? 0 ?></div>
                            </div>
                            <div class="p-2 bg-red-50 rounded-lg">
                                <div class="text-xs text-gray-600">Absent Days</div>
                                <div class="text-lg font-bold text-red-700"><?= $salary_record['absent_days'] ?? 0 ?></div>
                            </div>
                            <div class="p-2 bg-purple-50 rounded-lg">
                                <div class="text-xs text-gray-600">Overtime Hours</div>
                                <div class="text-lg font-bold text-purple-700"><?= number_format($salary_record['overtime_hours'] ?? 0, 1) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Earnings</h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="py-2 text-left text-gray-600">Description</th>
                                        <th class="py-2 text-right text-gray-600">Amount (Rs.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-2">Basic Salary</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['basic_salary'], 2) ?></td>
                                    </tr>
                                    <?php if ($salary_record['transport_allowance'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Transport Allowance</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['transport_allowance'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($salary_record['meal_allowance'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Meal Allowance</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['meal_allowance'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($salary_record['housing_allowance'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Housing Allowance</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['housing_allowance'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($salary_record['other_allowance'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Other Allowances</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['other_allowance'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($salary_record['overtime_amount'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Overtime</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['overtime_amount'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="border-t border-gray-200 font-medium">
                                        <td class="py-2">Gross Earnings</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['gross_salary'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Deductions</h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="py-2 text-left text-gray-600">Description</th>
                                        <th class="py-2 text-right text-gray-600">Amount (Rs.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-2">EPF Employee (<?= number_format(($salary_record['epf_employee'] / $salary_record['basic_salary']) * 100, 0) // Calculate percentage dynamically ?>%)</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['epf_employee'], 2) ?></td>
                                    </tr>
                                    <?php if ($salary_record['paye_tax'] > 0): ?>
                                    <tr>
                                        <td class="py-2">PAYE Tax</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['paye_tax'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($salary_record['loan_deductions'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Loan Repayments</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['loan_deductions'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($salary_record['other_deductions'] > 0): ?>
                                    <tr>
                                        <td class="py-2">Other Deductions</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['other_deductions'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                     <tr class="border-t border-gray-200 font-medium">
                                        <td class="py-2">Total Deductions</td>
                                        <td class="py-2 text-right"><?= number_format($salary_record['total_deductions'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <h4 class="text-sm font-medium text-blue-700 mb-2">Net Pay</h4>
                            <div class="text-2xl font-bold text-blue-800">Rs. <?= number_format($salary_record['net_salary'], 2) ?></div>
                            <div class="text-xs text-gray-600 mt-2">
                                <?= ucfirst(getWords($salary_record['net_salary'])) // Using the function from functions.php ?> Rupees Only
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Employer Contributions</h4>
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr>
                                        <td class="py-1">EPF Employer (<?= number_format(($salary_record['epf_employer'] / $salary_record['basic_salary']) * 100, 0) // Calculate percentage dynamically ?>%)</td>
                                        <td class="py-1 text-right"><?= number_format($salary_record['epf_employer'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="py-1">ETF (<?= number_format(($salary_record['etf'] / $salary_record['basic_salary']) * 100, 0) // Calculate percentage dynamically ?>%)</td>
                                        <td class="py-1 text-right"><?= number_format($salary_record['etf'], 2) ?></td>
                                    </tr>
                                    <tr class="border-t border-gray-300 font-medium">
                                        <td class="py-1">Total Contributions</td>
                                        <td class="py-1 text-right">
                                            <?= number_format($salary_record['epf_employer'] + $salary_record['etf'], 2) ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="p-6 border-t border-gray-200 bg-gray-50">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Year-to-Date Totals (<?= $year ?>)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-300 text-gray-600">
                                        <th class="py-2 text-left px-1">Gross Earnings</th>
                                        <th class="py-2 text-right px-1">EPF Employee</th>
                                        <th class="py-2 text-right px-1">EPF Employer</th>
                                        <th class="py-2 text-right px-1">ETF</th>
                                        <th class="py-2 text-right px-1">Net Pay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-2 font-medium px-1">Rs. <?= number_format($ytd_totals['gross'], 2) ?></td>
                                        <td class="py-2 text-right px-1">Rs. <?= number_format($ytd_totals['epf_employee'], 2) ?></td>
                                        <td class="py-2 text-right px-1">Rs. <?= number_format($ytd_totals['epf_employer'], 2) ?></td>
                                        <td class="py-2 text-right px-1">Rs. <?= number_format($ytd_totals['etf'], 2) ?></td>
                                        <td class="py-2 text-right font-medium px-1">Rs. <?= number_format($ytd_totals['net'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="p-4 border-t border-gray-200 text-center text-xs text-gray-500">
                        <p>This is a computer-generated payslip and does not require a signature.</p>
                         <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
                    </div>
                </div>
                 <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function changeEmployee(staffId) {
    if (staffId) {
        // Redirect to the same page but only with staff_id, letting the page load the latest payslip or prompt selection
        window.location.href = 'payslip.php?staff_id=' + staffId;
    } else {
         window.location.href = 'payslip.php'; // Go back to initial state if no employee selected
    }
}

function changePayslip(salaryId) {
    const employeeSelect = document.getElementById('employee_select');
    const staffId = employeeSelect ? employeeSelect.value : <?= $staff_id ?: 'null' ?>;

    if (salaryId) {
        // Redirect using the selected salary_id, ensuring staff_id is also present if known
         window.location.href = 'payslip.php?salary_id=' + salaryId + (staffId ? '&staff_id=' + staffId : '');
    } else if (staffId) {
        // If 'Select Period' is chosen, reload with just staff_id
        window.location.href = 'payslip.php?staff_id=' + staffId;
    }
}

</script>

<?php
// --- The getWords() function that was here has been REMOVED ---
// --- It is now correctly included via require_once('functions.php') at the top ---

// Include footer
include_once('../../includes/footer.php');
?>