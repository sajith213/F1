<?php
/**
 * Pay Salary
 * 
 * This file handles processing individual salary payments
 */

// Set page title
$page_title = "Process Salary Payment";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <a href="view_salaries.php">View Salaries</a> / <span>Process Payment</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Check permissions
if (!has_permission('approve_payments')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Get salary_id from URL
$salary_id = isset($_GET['salary_id']) ? intval($_GET['salary_id']) : null;

$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_date = $_POST['payment_date'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $reference_no = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $salary_id = $_POST['salary_id'] ?? 0;
    
    // Validate inputs
    if (empty($payment_date)) {
        $error_message = "Payment date is required.";
    } elseif (empty($payment_method)) {
        $error_message = "Payment method is required.";
    } elseif (empty($salary_id)) {
        $error_message = "Invalid salary record.";
    } else {
        // Process the payment
        $result = processSalaryPayment($salary_id, $payment_date, $payment_method, $reference_no, $_SESSION['user_id'], $notes);
        
        if ($result === true) {
            $success_message = "Payment processed successfully.";
            // Get month and year to redirect back to view salaries
            $redirect_query = "SELECT YEAR(STR_TO_DATE(CONCAT(pay_period, '-01'), '%Y-%m-%d')) as year, 
                              MONTH(STR_TO_DATE(CONCAT(pay_period, '-01'), '%Y-%m-%d')) as month
                              FROM salary_records WHERE salary_id = ?";
            $redirect_stmt = $conn->prepare($redirect_query);
            $redirect_stmt->bind_param("i", $salary_id);
            $redirect_stmt->execute();
            $redirect_result = $redirect_stmt->get_result();
            $redirect_data = $redirect_result->fetch_assoc();
            $redirect_stmt->close();
            
            // Redirect after a delay
            echo '<meta http-equiv="refresh" content="2;url=view_salaries.php?year=' . $redirect_data['year'] . '&month=' . $redirect_data['month'] . '">';
        } else {
            $error_message = $result;
        }
    }
}

// Get salary record details
$salary_record = null;
$employee = null;

if ($salary_id) {
    $query = "SELECT sr.*, s.first_name, s.last_name, s.staff_code, s.department, s.position
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.salary_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $salary_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $salary_record = $result->fetch_assoc();
        
        // Create employee array for easy access
        $employee = [
            'full_name' => $salary_record['first_name'] . ' ' . $salary_record['last_name'],
            'staff_code' => $salary_record['staff_code'],
            'department' => $salary_record['department'],
            'position' => $salary_record['position']
        ];
        
        // Check if already paid
        if ($salary_record['payment_status'] !== 'pending') {
            $error_message = "This salary has already been " . $salary_record['payment_status'] . ".";
        }
    } else {
        $error_message = "Salary record not found.";
    }
    
    $stmt->close();
}

// Format pay period for display
$pay_period_display = '';
if ($salary_record) {
    $pay_period_parts = explode('-', $salary_record['pay_period']);
    if (count($pay_period_parts) == 2) {
        $year = $pay_period_parts[0];
        $month = $pay_period_parts[1];
        $pay_period_display = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    }
}
?>

<!-- Main Content -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Process Salary Payment</h2>
    </div>
    
    <div class="p-4">
        <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?= $success_message ?></p>
            <p class="mt-2 text-sm">Redirecting to salary list...</p>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?= $error_message ?></p>
            <?php if ($salary_record && $salary_record['payment_status'] !== 'pending'): ?>
            <p class="mt-2">
                <a href="view_salaries.php" class="font-medium underline">Return to salary list</a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($salary_record && $salary_record['payment_status'] === 'pending'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Employee & Salary Details -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="text-md font-medium text-gray-700 mb-2 pb-2 border-b border-gray-300">Employee Details</h3>
                <table class="w-full text-sm">
                    <tr>
                        <td class="py-2 text-gray-600">Employee Name:</td>
                        <td class="py-2 font-medium"><?= htmlspecialchars($employee['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Employee ID:</td>
                        <td class="py-2 font-medium"><?= htmlspecialchars($employee['staff_code']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Department:</td>
                        <td class="py-2 font-medium"><?= htmlspecialchars($employee['department'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Position:</td>
                        <td class="py-2 font-medium"><?= htmlspecialchars($employee['position']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Pay Period:</td>
                        <td class="py-2 font-medium"><?= $pay_period_display ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Salary Summary -->
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h3 class="text-md font-medium text-blue-700 mb-2 pb-2 border-b border-blue-300">Salary Summary</h3>
                <table class="w-full text-sm">
                    <tr>
                        <td class="py-2 text-gray-600">Gross Salary:</td>
                        <td class="py-2 font-medium">Rs. <?= number_format($salary_record['gross_salary'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Total Deductions:</td>
                        <td class="py-2 font-medium">Rs. <?= number_format($salary_record['total_deductions'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">EPF (Employee):</td>
                        <td class="py-2 font-medium">Rs. <?= number_format($salary_record['epf_employee'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">EPF (Employer):</td>
                        <td class="py-2 font-medium">Rs. <?= number_format($salary_record['epf_employer'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">ETF:</td>
                        <td class="py-2 font-medium">Rs. <?= number_format($salary_record['etf'], 2) ?></td>
                    </tr>
                    <tr class="border-t border-blue-300 font-bold">
                        <td class="py-2 text-blue-800">Net Salary:</td>
                        <td class="py-2 text-blue-800 text-lg">Rs. <?= number_format($salary_record['net_salary'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Payment Processing Form -->
        <form method="post" action="pay_salary.php" class="bg-white border rounded-lg p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Payment Details</h3>
            
            <input type="hidden" name="salary_id" value="<?= $salary_id ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                    <input type="date" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                </div>
                
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                    <select id="payment_method" name="payment_method" 
                           class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" id="reference_no" name="reference_no" placeholder="Payment reference" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="2" 
                       class="form-textarea w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                       placeholder="Additional payment notes"></textarea>
            </div>
            
            <div class="border-t border-gray-200 pt-4 mt-4">
                <div class="flex items-center mb-4">
                    <input id="confirm_payment" name="confirm_payment" type="checkbox" required
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <label for="confirm_payment" class="ml-2 block text-sm text-gray-900">
                        I confirm that the payment of <strong>Rs. <?= number_format($salary_record['net_salary'], 2) ?></strong> to <strong><?= htmlspecialchars($employee['full_name']) ?></strong> is correct.
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="view_salaries.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </a>
                    <button type="submit" name="process_payment" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-money-bill-wave mr-2"></i> Process Payment
                    </button>
                </div>
            </div>
        </form>
        <?php elseif (!$salary_record): ?>
        <div class="text-center py-8">
            <div class="text-3xl text-gray-400 mb-4">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <p class="text-gray-600 mb-4">No salary record found or invalid record ID.</p>
            <a href="view_salaries.php" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i> Return to Salary List
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Process a salary payment
 * 
 * @param int $salary_id The salary record ID
 * @param string $payment_date Payment date (Y-m-d)
 * @param string $payment_method Payment method
 * @param string $reference_no Reference number
 * @param int $user_id User ID processing the payment
 * @param string $notes Additional notes
 * @return bool|string True if successful, error message on failure
 */
function processSalaryPayment($salary_id, $payment_date, $payment_method, $reference_no, $user_id, $notes = '') {
    global $conn;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Check if salary is still pending
        $check_query = "SELECT sr.*, s.first_name, s.last_name 
                       FROM salary_records sr
                       JOIN staff s ON sr.staff_id = s.staff_id
                       WHERE sr.salary_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $salary_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $conn->rollback();
            return "Salary record not found.";
        }
        
        $salary = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($salary['payment_status'] !== 'pending') {
            $conn->rollback();
            return "This salary has already been " . $salary['payment_status'] . ".";
        }
        
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
            return "Failed to update payment status. The record may have been modified by another user.";
        }
        
        // Insert payment history record
        $payment_query = "INSERT INTO salary_payments 
                        (salary_id, payment_date, amount, payment_method, reference_no, notes, processed_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        if (empty($notes)) {
            $notes = "Salary payment for " . $salary['first_name'] . ' ' . $salary['last_name'] . " - " . $salary['pay_period'];
        }
        
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