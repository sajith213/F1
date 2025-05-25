<?php
/**
 * Loans Management
 * 
 * This file manages employee loans and salary advances, 
 * including adding new loans, updating payments, and viewing loan history.
 */

// Set page title
$page_title = "Employee Loans & Advances";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>Loans & Advances</span>';

// Include header
include_once('../../includes/header.php');

// Include auth.php for user functions 
require_once('../../includes/auth.php');  // ADD THIS LINE HERE

// Include module functions
require_once('functions.php');

// Check for permissions
if (!has_permission('manage_loans')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Get current user ID
$current_user_id = get_current_user_id();

// Initialize variables
$success_message = '';
$error_message = '';
$view_loan_id = isset($_GET['view_loan']) ? intval($_GET['view_loan']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$loan_status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Process loan payment update
if (isset($_POST['update_payment'])) {
    $loan_id = intval($_POST['loan_id']);
    $payment_amount = floatval($_POST['payment_amount']);
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        $error_message = "Payment amount must be greater than zero.";
    } else {
        $result = updateLoanPayment($loan_id, $payment_amount, $current_user_id);
        
        if ($result === true) {
            $success_message = "Loan payment updated successfully.";
        } else {
            $error_message = $result; // Error message from function
        }
    }
}

// Process adding new loan
if (isset($_POST['add_loan'])) {
    // Validate inputs
    $loan_staff_id = intval($_POST['staff_id']);
    $loan_amount = floatval($_POST['loan_amount']);
    $monthly_deduction = floatval($_POST['monthly_deduction']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $loan_type = $_POST['loan_type'];
    $notes = $_POST['notes'];
    
    // Basic validation
    if ($loan_staff_id <= 0) {
        $error_message = "Please select a valid employee.";
    } elseif ($loan_amount <= 0) {
        $error_message = "Loan amount must be greater than zero.";
    } elseif ($monthly_deduction <= 0 || $monthly_deduction > $loan_amount) {
        $error_message = "Monthly deduction must be greater than zero and less than or equal to loan amount.";
    } elseif (empty($start_date)) {
        $error_message = "Start date is required.";
    } else {
        // Prepare loan data
        $loan_data = [
            'staff_id' => $loan_staff_id,
            'loan_amount' => $loan_amount,
            'monthly_deduction' => $monthly_deduction,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'loan_type' => $loan_type,
            'notes' => $notes,
            'created_by' => $current_user_id
        ];
        
        // Add loan to database
        $result = addEmployeeLoan($loan_data);
        
        if ($result === true) {
            $success_message = "Loan added successfully.";
            // Reset staff_id to show all loans after adding
            $staff_id = 0;
        } else {
            $error_message = $result; // Error message from function
        }
    }
}

// Get all employees for dropdown
function getAllEmployees() {
    global $conn;
    
    $query = "SELECT staff_id, CONCAT(first_name, ' ', last_name) AS employee_name, staff_code 
              FROM staff 
              WHERE status = 'active' 
              ORDER BY first_name, last_name";
    
    $result = $conn->query($query);
    
    $employees = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    
    return $employees;
}

$employees = getAllEmployees();

// Get employee details if staff_id is provided
$employee_details = null;
if ($staff_id > 0) {
    $query = "SELECT staff_id, CONCAT(first_name, ' ', last_name) AS employee_name, 
              staff_code, position, department 
              FROM staff 
              WHERE staff_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $employee_details = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Get loans based on filter
$loans = [];
if ($staff_id > 0) {
    $loans = getEmployeeLoans($staff_id, $loan_status_filter);
} else {
    // Get loans summary report
    $loan_report = getLoanSummaryReport($loan_status_filter);
    $loans = $loan_report['loans'];
}

// Get loan details for viewing
$loan_details = null;
if ($view_loan_id > 0) {
    $loan_query = "SELECT l.*, 
                  s.first_name, s.last_name, s.staff_code, s.position, 
                  u.full_name AS created_by_name 
                  FROM employee_loans l 
                  JOIN staff s ON l.staff_id = s.staff_id 
                  LEFT JOIN users u ON l.created_by = u.user_id 
                  WHERE l.loan_id = ?";
    
    $loan_stmt = $conn->prepare($loan_query);
    $loan_stmt->bind_param("i", $view_loan_id);
    $loan_stmt->execute();
    $loan_result = $loan_stmt->get_result();
    
    if ($loan_result && $loan_result->num_rows > 0) {
        $loan_details = $loan_result->fetch_assoc();
    }
    
    $loan_stmt->close();
}

// Helper functions
function getLoanStatusBadge($status) {
    switch ($status) {
        case 'active':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>';
        case 'completed':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Completed</span>';
        case 'cancelled':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Cancelled</span>';
        default:
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>';
    }
}

function getLoanTypeBadge($type) {
    switch ($type) {
        case 'advance':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Salary Advance</span>';
        case 'loan':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">Loan</span>';
        case 'other':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Other</span>';
        default:
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>';
    }
}

function formatCurrency($amount) {
    return number_format((float)$amount, 2, '.', ',');
}

// Calculate the number of installments
function calculateInstallments($loanAmount, $monthlyDeduction) {
    if ($monthlyDeduction <= 0) return 0;
    return ceil($loanAmount / $monthlyDeduction);
}

// Calculate the estimated end date
function calculateEndDate($startDate, $installments) {
    $date = new DateTime($startDate);
    $date->modify('+' . ($installments - 1) . ' months');
    return $date->format('Y-m-d');
}
?>

<!-- Page Content -->
<div class="flex flex-wrap mb-6">
    <!-- Action Buttons -->
    <div class="w-full mb-4 flex justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800">
            <?= $staff_id > 0 ? 'Loans for ' . htmlspecialchars($employee_details['employee_name']) : 'All Employee Loans' ?>
        </h2>
        
        <div class="flex space-x-2">
            <?php if ($staff_id > 0): ?>
                <a href="loans.php" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-arrow-left mr-1"></i> Back to All Loans
                </a>
            <?php endif; ?>
            
            <button onclick="openAddLoanModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-plus mr-1"></i> New Loan
            </button>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="w-full mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="w-full mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Loan Details Modal -->
    <?php if ($loan_details): ?>
        <div class="w-full mb-4">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-blue-50 px-4 py-3 border-b border-blue-100 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Loan Details #<?= $loan_details['loan_id'] ?>
                    </h3>
                    <a href="<?= $staff_id > 0 ? "loans.php?staff_id={$staff_id}" : "loans.php" ?>" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
                
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Employee</h4>
                            <p class="text-lg font-semibold text-gray-800">
                                <?= htmlspecialchars($loan_details['first_name'] . ' ' . $loan_details['last_name']) ?>
                                <span class="text-sm font-normal text-gray-600">(<?= htmlspecialchars($loan_details['staff_code']) ?>)</span>
                            </p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($loan_details['position']) ?></p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Loan Information</h4>
                            <div class="flex items-center mb-1">
                                <p class="mr-2">Type:</p>
                                <?= getLoanTypeBadge($loan_details['loan_type']) ?>
                            </div>
                            <div class="flex items-center">
                                <p class="mr-2">Status:</p>
                                <?= getLoanStatusBadge($loan_details['status']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Total Loan Amount</h4>
                            <p class="text-xl font-bold text-blue-600">Rs. <?= formatCurrency($loan_details['loan_amount']) ?></p>
                        </div>
                        
                        <div class="bg-green-50 p-3 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Paid Amount</h4>
                            <p class="text-xl font-bold text-green-600">Rs. <?= formatCurrency($loan_details['loan_amount'] - $loan_details['remaining_amount']) ?></p>
                        </div>
                        
                        <div class="bg-orange-50 p-3 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Remaining Amount</h4>
                            <p class="text-xl font-bold text-orange-600">Rs. <?= formatCurrency($loan_details['remaining_amount']) ?></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Payment Schedule</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500">Monthly Deduction</p>
                                    <p class="font-semibold">Rs. <?= formatCurrency($loan_details['monthly_deduction']) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Start Date</p>
                                    <p class="font-semibold"><?= date('d M, Y', strtotime($loan_details['start_date'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">End Date</p>
                                    <p class="font-semibold"><?= $loan_details['end_date'] ? date('d M, Y', strtotime($loan_details['end_date'])) : 'Not set' ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Progress</h4>
                            <?php 
                            $progress = ($loan_details['loan_amount'] - $loan_details['remaining_amount']) / $loan_details['loan_amount'] * 100;
                            $progress = min(100, max(0, $progress)); // Ensure progress is between 0 and 100
                            ?>
                            <div class="h-4 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-600" style="width: <?= $progress ?>%"></div>
                            </div>
                            <p class="text-xs text-right mt-1"><?= round($progress) ?>% completed</p>
                        </div>
                    </div>
                    
                    <?php if ($loan_details['notes']): ?>
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Notes</h4>
                            <p class="text-gray-700 border p-3 bg-gray-50 rounded"><?= nl2br(htmlspecialchars($loan_details['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-xs text-gray-500">Created By</p>
                            <p class="font-semibold"><?= htmlspecialchars($loan_details['created_by_name'] ?? 'System') ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Created On</p>
                            <p class="font-semibold"><?= date('d M, Y H:i', strtotime($loan_details['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <!-- Action Section -->
                    <?php if ($loan_details['status'] === 'active'): ?>
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Record Payment</h4>
                            <form action="" method="POST" class="flex items-center space-x-4">
                                <input type="hidden" name="loan_id" value="<?= $loan_details['loan_id'] ?>">
                                
                                <div class="flex-1">
                                    <label for="payment_amount" class="block text-xs font-medium text-gray-700 mb-1">Payment Amount (Rs.)</label>
                                    <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0.01" max="<?= $loan_details['remaining_amount'] ?>" 
                                           value="<?= min($loan_details['monthly_deduction'], $loan_details['remaining_amount']) ?>" 
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                                </div>
                                
                                <div class="flex-none">
                                    <button type="submit" name="update_payment" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        Record Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="w-full mb-4">
        <div class="bg-white rounded-lg shadow-md p-4">
            <form action="" method="GET" class="md:flex md:items-end md:space-x-4">
                <?php if ($staff_id > 0): ?>
                    <input type="hidden" name="staff_id" value="<?= $staff_id ?>">
                <?php else: ?>
                    <div class="mb-4 md:mb-0 md:flex-1">
                        <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee</label>
                        <select id="staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= $employee['staff_id'] ?>" <?= ($staff_id == $employee['staff_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($employee['employee_name']) ?> (<?= htmlspecialchars($employee['staff_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4 md:mb-0 md:w-48">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Loan Status</label>
                    <select id="status" name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="all" <?= ($loan_status_filter == 'all') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= ($loan_status_filter == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= ($loan_status_filter == 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= ($loan_status_filter == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="md:flex-none">
                    <button type="submit" class="w-full md:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Loans Table -->
    <div class="w-full">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if ($staff_id <= 0): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loan ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Deduction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="<?= ($staff_id <= 0) ? 9 : 8 ?>" class="px-6 py-4 text-sm text-gray-500 text-center">
                                    No loans found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <?php if ($staff_id <= 0): ?>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($loan['staff_code']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        #<?= $loan['loan_id'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= getLoanTypeBadge($loan['loan_type']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        Rs. <?= formatCurrency($loan['loan_amount']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        Rs. <?= formatCurrency($loan['remaining_amount']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        Rs. <?= formatCurrency($loan['monthly_deduction']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d M, Y', strtotime($loan['start_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= getLoanStatusBadge($loan['status']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="loans.php?<?= $staff_id > 0 ? "staff_id={$staff_id}&" : "" ?>view_loan=<?= $loan['loan_id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($loan['status'] === 'active'): ?>
                                            <a href="#" onclick="openRecordPaymentModal(<?= $loan['loan_id'] ?>, <?= $loan['remaining_amount'] ?>, <?= $loan['monthly_deduction'] ?>)" 
                                               class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Loan Modal -->
<div id="addLoanModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Add New Loan / Advance</h3>
            <button type="button" onclick="closeAddLoanModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="" method="POST" id="addLoanForm">
            <div class="px-6 py-4 overflow-y-auto" style="max-height: 70vh;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="modal_staff_id" class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                        <select id="modal_staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                            <option value="">Select an Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= $employee['staff_id'] ?>" <?= ($staff_id == $employee['staff_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($employee['employee_name']) ?> (<?= htmlspecialchars($employee['staff_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="loan_type" class="block text-sm font-medium text-gray-700 mb-1">Loan Type</label>
                        <select id="loan_type" name="loan_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                            <option value="advance">Salary Advance</option>
                            <option value="loan">Loan</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="loan_amount" class="block text-sm font-medium text-gray-700 mb-1">Loan Amount (Rs.)</label>
                        <input type="number" id="loan_amount" name="loan_amount" step="0.01" min="0.01" 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                               required oninput="updateCalculations()">
                    </div>
                    
                    <div>
                        <label for="monthly_deduction" class="block text-sm font-medium text-gray-700 mb-1">Monthly Deduction (Rs.)</label>
                        <input type="number" id="monthly_deduction" name="monthly_deduction" step="0.01" min="0.01" 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                               required oninput="updateCalculations()">
                    </div>
                </div>
                
                <!-- Repayment Preview Section -->
                <div class="bg-gray-50 p-3 rounded-lg mb-4 hidden" id="repaymentPreview">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Repayment Preview</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Number of Installments</p>
                            <p class="font-semibold" id="installments">0</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Estimated Completion</p>
                            <p class="font-semibold" id="estimated_completion">-</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                               required oninput="updateCalculations()">
                    </div>
                    
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date" 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="text-xs text-gray-500 mt-1">Leave blank to auto-calculate based on installments</p>
                    </div>
                </div>
                
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3" 
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button type="button" onclick="closeAddLoanModal()" class="mr-2 px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" name="add_loan" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Loan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Record Loan Payment</h3>
            <button type="button" onclick="closeRecordPaymentModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="" method="POST" id="recordPaymentForm">
            <input type="hidden" id="payment_loan_id" name="loan_id" value="">
            
            <div class="px-6 py-4">
                <div class="mb-4">
                    <label for="modal_payment_amount" class="block text-sm font-medium text-gray-700 mb-1">Payment Amount (Rs.)</label>
                    <input type="number" id="modal_payment_amount" name="payment_amount" step="0.01" min="0.01" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                           required>
                    <p class="text-xs text-gray-500 mt-1">Max Payment: Rs. <span id="max_payment">0.00</span></p>
                </div>
                
                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        This payment will be recorded immediately and will reduce the remaining loan balance.
                    </p>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button type="button" onclick="closeRecordPaymentModal()" class="mr-2 px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" name="update_payment" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions for Add Loan
function openAddLoanModal() {
    document.getElementById('addLoanModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeAddLoanModal() {
    document.getElementById('addLoanModal').classList.add('hidden');
    document.body.style.overflow = ''; // Restore scrolling
}

// Modal functions for Record Payment
function openRecordPaymentModal(loanId, remainingAmount, monthlyDeduction) {
    document.getElementById('payment_loan_id').value = loanId;
    document.getElementById('max_payment').textContent = remainingAmount.toFixed(2);
    
    // Set default payment amount to monthly deduction or remaining amount (whichever is smaller)
    const paymentAmount = Math.min(monthlyDeduction, remainingAmount);
    document.getElementById('modal_payment_amount').value = paymentAmount.toFixed(2);
    document.getElementById('modal_payment_amount').max = remainingAmount;
    
    document.getElementById('recordPaymentModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeRecordPaymentModal() {
    document.getElementById('recordPaymentModal').classList.add('hidden');
    document.body.style.overflow = ''; // Restore scrolling
}

// Update calculation for loan preview
function updateCalculations() {
    const loanAmount = parseFloat(document.getElementById('loan_amount').value) || 0;
    const monthlyDeduction = parseFloat(document.getElementById('monthly_deduction').value) || 0;
    const startDate = document.getElementById('start_date').value;
    
    if (loanAmount > 0 && monthlyDeduction > 0 && startDate) {
        // Calculate number of installments
        const installments = Math.ceil(loanAmount / monthlyDeduction);
        document.getElementById('installments').textContent = installments;
        
        // Calculate estimated completion date
        const start = new Date(startDate);
        const completion = new Date(start);
        completion.setMonth(completion.getMonth() + installments - 1);
        
        document.getElementById('estimated_completion').textContent = completion.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        
        // Update end date field (if it's empty)
        if (!document.getElementById('end_date').value) {
            document.getElementById('end_date').value = completion.toISOString().split('T')[0];
        }
        
        // Show the preview section
        document.getElementById('repaymentPreview').classList.remove('hidden');
    } else {
        // Hide the preview section if data is incomplete
        document.getElementById('repaymentPreview').classList.add('hidden');
    }
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const addLoanModal = document.getElementById('addLoanModal');
    if (event.target === addLoanModal) {
        closeAddLoanModal();
    }
    
    const recordPaymentModal = document.getElementById('recordPaymentModal');
    if (event.target === recordPaymentModal) {
        closeRecordPaymentModal();
    }
});

// Initialize calculations on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initial calculation
    updateCalculations();
});
</script>

<?php
// Include footer
include_once('../../includes/footer.php');
?>