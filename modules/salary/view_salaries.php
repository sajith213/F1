<?php
/**
 * View Salaries
 * 
 * This file allows viewing processed salaries with filtering options
 */

// Set page title
$page_title = "View Processed Salaries";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>View Salaries</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Check permissions
if (!has_permission('view_salary_reports')) {
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
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$pay_period = sprintf('%04d-%02d', $year, $month);

$success_message = '';
$error_message = '';

// Process form submission for batch payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    if (has_permission('approve_payments')) {
        $salary_ids = $_POST['salary_ids'] ?? [];
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $reference_no = $_POST['reference_no'] ?? '';
        
        if (empty($salary_ids)) {
            $error_message = "No salaries selected for payment processing.";
        } else {
            $success_count = 0;
            
            foreach ($salary_ids as $salary_id) {
                $result = processSalaryPayment($salary_id, $payment_date, $payment_method, $reference_no, $_SESSION['user_id']);
                
                if ($result === true) {
                    $success_count++;
                }
            }
            
            if ($success_count > 0) {
                $success_message = "$success_count salary payments processed successfully.";
            } else {
                $error_message = "Failed to process any payments. Please try again.";
            }
        }
    } else {
        $error_message = "You do not have permission to process payments.";
    }
}

// Get all departments for filter
$departments_query = "SELECT DISTINCT s.department FROM staff s 
                     JOIN salary_records sr ON s.staff_id = sr.staff_id 
                     WHERE s.department IS NOT NULL AND s.department != '' AND sr.pay_period = ? 
                     ORDER BY s.department";
$departments_stmt = $conn->prepare($departments_query);
$departments_stmt->bind_param("s", $pay_period);
$departments_stmt->execute();
$departments_result = $departments_stmt->get_result();
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}
$departments_stmt->close();

// Build query for salary records
$query = "SELECT sr.*, s.first_name, s.last_name, s.staff_code, s.department, s.position, 
                 u.full_name as calculated_by_name
          FROM salary_records sr
          JOIN staff s ON sr.staff_id = s.staff_id
          LEFT JOIN users u ON sr.calculated_by = u.user_id
          WHERE sr.pay_period = ?";
$params = [$pay_period];
$param_types = "s";

if (!empty($department)) {
    $query .= " AND s.department = ?";
    $params[] = $department;
    $param_types .= "s";
}

if ($staff_id) {
    $query .= " AND sr.staff_id = ?";
    $params[] = $staff_id;
    $param_types .= "i";
}

if (!empty($status)) {
    $query .= " AND sr.payment_status = ?";
    $params[] = $status;
    $param_types .= "s";
}

$query .= " ORDER BY s.department, s.first_name, s.last_name";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$salary_records = [];
while ($row = $result->fetch_assoc()) {
    $salary_records[] = $row;
}
$stmt->close();

// Calculate summary totals
$total_gross = 0;
$total_net = 0;
$total_epf_employee = 0;
$total_epf_employer = 0;
$total_etf = 0;
$paid_count = 0;
$pending_count = 0;

foreach ($salary_records as $record) {
    $total_gross += $record['gross_salary'];
    $total_net += $record['net_salary'];
    $total_epf_employee += $record['epf_employee'];
    $total_epf_employer += $record['epf_employer'];
    $total_etf += $record['etf'];
    
    if ($record['payment_status'] === 'paid') {
        $paid_count++;
    } elseif ($record['payment_status'] === 'pending') {
        $pending_count++;
    }
}

// Format month name
$month_name = date('F', mktime(0, 0, 0, $month, 10));
?>

<!-- Main Content -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">View Processed Salaries</h2>
        <div>
            <a href="process_salaries.php?year=<?= $year ?>&month=<?= $month ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-calculator mr-2"></i> Process More Salaries
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
        
        <!-- Filters -->
        <div class="mb-6">
            <form method="get" action="view_salaries.php" id="filter-form" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                    <select id="status" name="status" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= $status == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <div class="text-sm text-gray-600">Processed Salaries</div>
                <div class="text-xl font-bold text-blue-800"><?= count($salary_records) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                    <?= $paid_count ?> Paid / <?= $pending_count ?> Pending
                </div>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <div class="text-sm text-gray-600">Total Gross</div>
                <div class="text-xl font-bold text-green-800">Rs. <?= number_format($total_gross, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                    Before deductions
                </div>
            </div>
            
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <div class="text-sm text-gray-600">Total EPF/ETF</div>
                <div class="text-xl font-bold text-purple-800">Rs. <?= number_format($total_epf_employee + $total_epf_employer + $total_etf, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                    EPF: Rs. <?= number_format($total_epf_employee + $total_epf_employer, 2) ?> / ETF: Rs. <?= number_format($total_etf, 2) ?>
                </div>
            </div>
            
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <div class="text-sm text-gray-600">Total Net Pay</div>
                <div class="text-xl font-bold text-yellow-800">Rs. <?= number_format($total_net, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                    After all deductions
                </div>
            </div>
        </div>
        
        <?php if (count($salary_records) > 0): ?>
        <!-- Batch Payment Processing (only shown if pending payments exist) -->
        <?php if ($pending_count > 0 && has_permission('approve_payments')): ?>
        <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
            <h3 class="text-md font-medium text-blue-700 mb-2">Process Batch Payment</h3>
            <form id="payment-form" method="post" action="view_salaries.php" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="department" value="<?= htmlspecialchars($department) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                    <input type="date" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                </div>
                
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" id="reference_no" name="reference_no" placeholder="Optional" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" name="process_payment" id="batch-payment-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition" disabled>
                        <i class="fas fa-money-bill-wave mr-2"></i> Process Selected Payments
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Salary Records Table -->
        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($pending_count > 0 && has_permission('approve_payments')): ?>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        </th>
                        <?php endif; ?>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF/ETF</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Other Deductions</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($salary_records as $record): ?>
                    <tr>
                        <?php if ($pending_count > 0 && has_permission('approve_payments')): ?>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <input type="checkbox" name="salary_ids[]" value="<?= $record['salary_id'] ?>" 
                                   class="salary-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                   form="payment-form" <?= $record['payment_status'] !== 'pending' ? 'disabled' : '' ?>>
                        </td>
                        <?php endif; ?>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                            </div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($record['staff_code']) ?></div>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($record['department'] ?? 'N/A') ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                            Rs. <?= number_format($record['gross_salary'], 2) ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                            Rs. <?= number_format($record['epf_employee'] + $record['epf_employer'] + $record['etf'], 2) ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                            Rs. <?= number_format($record['loan_deductions'] + $record['other_deductions'], 2) ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            Rs. <?= number_format($record['net_salary'], 2) ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <?php
                            $status_class = '';
                            $status_text = '';
                            
                            switch ($record['payment_status']) {
                                case 'paid':
                                    $status_class = 'bg-green-100 text-green-800';
                                    $status_text = 'Paid';
                                    break;
                                case 'pending':
                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                    $status_text = 'Pending';
                                    break;
                                case 'cancelled':
                                    $status_class = 'bg-red-100 text-red-800';
                                    $status_text = 'Cancelled';
                                    break;
                                default:
                                    $status_class = 'bg-gray-100 text-gray-800';
                                    $status_text = $record['payment_status'];
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                            
                            <?php if ($record['payment_status'] === 'paid' && $record['payment_date']): ?>
                            <div class="text-xs text-gray-500 mt-1">
                                <?= date('d M Y', strtotime($record['payment_date'])) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="payslip.php?salary_id=<?= $record['salary_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View Payslip">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </a>
                            
                            <?php if ($record['payment_status'] === 'pending' && has_permission('approve_payments')): ?>
                            <a href="pay_salary.php?salary_id=<?= $record['salary_id'] ?>" class="text-green-600 hover:text-green-900 mr-3" title="Process Payment">
                                <i class="fas fa-money-bill-wave"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (has_permission('calculate_salaries')): ?>
                            <a href="salary_calculator.php?staff_id=<?= $record['staff_id'] ?>&year=<?= $year ?>&month=<?= $month ?>&force=1" class="text-purple-600 hover:text-purple-900" title="Recalculate">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Export and Reports -->
        <div class="mt-6 flex justify-between items-center">
            <div>
                <a href="#" onclick="exportData('csv')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
                    <i class="fas fa-file-csv mr-2"></i> Export to CSV
                </a>
                <a href="#" onclick="exportData('pdf')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                </a>
            </div>
            
            <div>
                <a href="epf_etf_report.php?year=<?= $year ?>&month=<?= $month ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-chart-bar mr-2"></i> EPF/ETF Report
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center py-6 bg-gray-50 rounded-lg border border-gray-200">
            <p class="text-gray-700">No processed salaries found for <?= $month_name ?> <?= $year ?><?= $department ? " in department: $department" : "" ?>.</p>
            
            <a href="process_salaries.php?year=<?= $year ?>&month=<?= $month ?>" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-calculator mr-2"></i> Process Salaries
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for handling selections and export functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const salaryCheckboxes = document.querySelectorAll('.salary-checkbox:not([disabled])');
    const batchPaymentBtn = document.getElementById('batch-payment-btn');
    
    // Function to update payment button state
    function updatePaymentButton() {
        const checkedBoxes = document.querySelectorAll('.salary-checkbox:checked').length;
        if (batchPaymentBtn) {
            batchPaymentBtn.disabled = checkedBoxes === 0;
        }
    }
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            salaryCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updatePaymentButton();
        });
    }
    
    // Individual checkbox change
    salaryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updatePaymentButton();
            
            // Update select all checkbox state
            if (selectAllCheckbox) {
                const allChecked = document.querySelectorAll('.salary-checkbox:not([disabled])').length === 
                                  document.querySelectorAll('.salary-checkbox:checked').length;
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
    
    // Initial button state
    updatePaymentButton();
});

// Export functionality (placeholder functions)
function exportData(format) {
    const year = <?= $year ?>;
    const month = <?= $month ?>;
    const department = '<?= addslashes($department) ?>';
    const status = '<?= addslashes($status) ?>';
    
    let url = `export_salaries.php?format=${format}&year=${year}&month=${month}`;
    
    if (department) {
        url += `&department=${encodeURIComponent(department)}`;
    }
    
    if (status) {
        url += `&status=${encodeURIComponent(status)}`;
    }
    
    window.location.href = url;
}
</script>

<?php
/**
 * Process a salary payment
 * 
 * @param int $salary_id The salary record ID
 * @param string $payment_date Payment date (Y-m-d)
 * @param string $payment_method Payment method
 * @param string $reference_no Reference number
 * @param int $user_id User ID processing the payment
 * @return bool|string True if successful, error message on failure
 */
function processSalaryPayment($salary_id, $payment_date, $payment_method, $reference_no, $user_id) {
    global $conn;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update salary record payment status
        $update_query = "UPDATE salary_records 
                       SET payment_status = 'paid', 
                           payment_date = ?, 
                           payment_method = ?, 
                           reference_no = ?, 
                           approved_by = ?,
                           updated_at = NOW() 
                       WHERE salary_id = ? AND payment_status = 'pending'";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssii", $payment_date, $payment_method, $reference_no, $user_id, $salary_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows == 0) {
            // No records updated (could be already paid or not found)
            $conn->rollback();
            return "Salary record not found or already processed.";
        }
        
        // Get salary record details
        $query = "SELECT staff_id, net_salary, pay_period FROM salary_records WHERE salary_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $salary_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $salary = $result->fetch_assoc();
        $stmt->close();
        
        // Insert payment history record
        $payment_query = "INSERT INTO salary_payments 
                        (salary_id, payment_date, amount, payment_method, reference_no, notes, processed_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $notes = "Salary payment for period: " . $salary['pay_period'];
        
        $payment_stmt = $conn->prepare($payment_query);
        $payment_stmt->bind_param("isdssis", 
                                  $salary_id, 
                                  $payment_date, 
                                  $salary['net_salary'], 
                                  $payment_method, 
                                  $reference_no, 
                                  $notes, 
                                  $user_id);
        $payment_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Roll back on error
        $conn->rollback();
        return "Error processing payment: " . $e->getMessage();
    }
}

// Include footer
include_once('../../includes/footer.php');
?>