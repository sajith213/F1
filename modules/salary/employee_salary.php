<?php
/**
 * Employee Salary Settings
 * 
 * This file manages individual employee salary configuration
 */

// Set page title
$page_title = "Employee Salary Settings";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>Employee Salary Settings</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');


// Check permissions
if (!has_permission('manage_salaries')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salary'])) {
    // Form data processing
    $salary_data = [
        'staff_id' => $_POST['staff_id'],
        'basic_salary' => $_POST['basic_salary'],
        'transport_allowance' => $_POST['transport_allowance'] ?? 0,
        'meal_allowance' => $_POST['meal_allowance'] ?? 0,
        'housing_allowance' => $_POST['housing_allowance'] ?? 0,
        'other_allowance' => $_POST['other_allowance'] ?? 0,
        'epf_employee_percent' => $_POST['epf_employee_percent'] ?? 8,
        'epf_employer_percent' => $_POST['epf_employer_percent'] ?? 12,
        'etf_percent' => $_POST['etf_percent'] ?? 3,
        'paye_tax_percent' => $_POST['paye_tax_percent'] ?? 0,
        'overtime_rate_regular' => $_POST['overtime_rate_regular'] ?? 1.5,
        'overtime_rate_holiday' => $_POST['overtime_rate_holiday'] ?? 2,
        'effective_date' => $_POST['effective_date'],
        'status' => 'active',
        'created_by' => $_SESSION['user_id'],
        'updated_by' => $_SESSION['user_id']
    ];

    $result = saveEmployeeSalaryInfo($salary_data);
    
    if ($result === true) {
        $success_message = "Salary information saved successfully.";
    } else {
        $error_message = $result;
    }
}

// Get staff ID from URL or select the first one as default
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;

// Get all staff for dropdown
$all_staff_query = "SELECT staff_id, first_name, last_name, staff_code FROM staff WHERE status = 'active' ORDER BY first_name, last_name";
$all_staff_result = $conn->query($all_staff_query);
$all_staff = [];
while ($row = $all_staff_result->fetch_assoc()) {
    $all_staff[] = $row;
    if (!$staff_id) {
        $staff_id = $row['staff_id']; // Set first staff as default if none selected
    }
}

// Get salary info for selected staff
$salary_info = $staff_id ? getEmployeeSalaryInfo($staff_id) : null;

// Staff details
$staff_details = null;
if ($staff_id) {
    $staff_query = "SELECT * FROM staff WHERE staff_id = ?";
    $staff_stmt = $conn->prepare($staff_query);
    $staff_stmt->bind_param("i", $staff_id);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    $staff_details = $staff_result->fetch_assoc();
    $staff_stmt->close();
}
?>

<!-- Employee Selection and Form -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Employee Salary Settings</h2>
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

        <!-- Employee Selection Dropdown -->
        <div class="mb-6">
            <label for="employee_select" class="block text-sm font-medium text-gray-700 mb-1">Select Employee</label>
            <select id="employee_select" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="window.location.href='employee_salary.php?staff_id='+this.value">
                <?php foreach ($all_staff as $staff): ?>
                    <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (' . $staff['staff_code'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($staff_details): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
            <div>
                <h3 class="text-md font-medium text-gray-700 mb-2">Employee Information</h3>
                <p><strong>Name:</strong> <?= htmlspecialchars($staff_details['first_name'] . ' ' . $staff_details['last_name']) ?></p>
                <p><strong>Code:</strong> <?= htmlspecialchars($staff_details['staff_code']) ?></p>
                <p><strong>Position:</strong> <?= htmlspecialchars($staff_details['position']) ?></p>
            </div>
            <div>
                <h3 class="text-md font-medium text-gray-700 mb-2">Employment Details</h3>
                <p><strong>Department:</strong> <?= htmlspecialchars($staff_details['department'] ?? 'Not Specified') ?></p>
                <p><strong>Hire Date:</strong> <?= date('d M, Y', strtotime($staff_details['hire_date'])) ?></p>
                <p><strong>Status:</strong> <?= ucfirst($staff_details['status']) ?></p>
            </div>
        </div>

        <form action="employee_salary.php" method="post">
            <input type="hidden" name="staff_id" value="<?= $staff_id ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Basic Salary -->
                <div>
                    <label for="basic_salary" class="block text-sm font-medium text-gray-700 mb-1">Basic Salary (Rs.) *</label>
                    <input type="number" id="basic_salary" name="basic_salary" step="0.01" min="0" 
                           value="<?= $salary_info ? $salary_info['basic_salary'] : '' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                </div>
                
                <!-- Effective Date -->
                <div>
                    <label for="effective_date" class="block text-sm font-medium text-gray-700 mb-1">Effective Date *</label>
                    <input type="date" id="effective_date" name="effective_date" 
                           value="<?= $salary_info ? $salary_info['effective_date'] : date('Y-m-d') ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                </div>
            </div>
            
            <!-- Allowances Section -->
            <h3 class="text-md font-medium text-gray-700 mb-2 mt-4">Allowances</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="transport_allowance" class="block text-sm font-medium text-gray-700 mb-1">Transport Allowance (Rs.)</label>
                    <input type="number" id="transport_allowance" name="transport_allowance" step="0.01" min="0" 
                           value="<?= $salary_info ? $salary_info['transport_allowance'] : '0.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="meal_allowance" class="block text-sm font-medium text-gray-700 mb-1">Meal Allowance (Rs.)</label>
                    <input type="number" id="meal_allowance" name="meal_allowance" step="0.01" min="0" 
                           value="<?= $salary_info ? $salary_info['meal_allowance'] : '0.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="housing_allowance" class="block text-sm font-medium text-gray-700 mb-1">Housing Allowance (Rs.)</label>
                    <input type="number" id="housing_allowance" name="housing_allowance" step="0.01" min="0" 
                           value="<?= $salary_info ? $salary_info['housing_allowance'] : '0.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="other_allowance" class="block text-sm font-medium text-gray-700 mb-1">Other Allowances (Rs.)</label>
                    <input type="number" id="other_allowance" name="other_allowance" step="0.01" min="0" 
                           value="<?= $salary_info ? $salary_info['other_allowance'] : '0.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
            </div>
            
            <!-- Deductions and Rates Section -->
            <h3 class="text-md font-medium text-gray-700 mb-2 mt-4">Deductions & Rates</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="epf_employee_percent" class="block text-sm font-medium text-gray-700 mb-1">EPF Employee (%)</label>
                    <input type="number" id="epf_employee_percent" name="epf_employee_percent" step="0.01" min="0" max="100" 
                           value="<?= $salary_info ? $salary_info['epf_employee_percent'] : '8.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="epf_employer_percent" class="block text-sm font-medium text-gray-700 mb-1">EPF Employer (%)</label>
                    <input type="number" id="epf_employer_percent" name="epf_employer_percent" step="0.01" min="0" max="100" 
                           value="<?= $salary_info ? $salary_info['epf_employer_percent'] : '12.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="etf_percent" class="block text-sm font-medium text-gray-700 mb-1">ETF (%)</label>
                    <input type="number" id="etf_percent" name="etf_percent" step="0.01" min="0" max="100" 
                           value="<?= $salary_info ? $salary_info['etf_percent'] : '3.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="paye_tax_percent" class="block text-sm font-medium text-gray-700 mb-1">PAYE Tax (%)</label>
                    <input type="number" id="paye_tax_percent" name="paye_tax_percent" step="0.01" min="0" max="100" 
                           value="<?= $salary_info ? $salary_info['paye_tax_percent'] : '0.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="overtime_rate_regular" class="block text-sm font-medium text-gray-700 mb-1">Overtime Rate (Regular)</label>
                    <input type="number" id="overtime_rate_regular" name="overtime_rate_regular" step="0.01" min="1" 
                           value="<?= $salary_info ? $salary_info['overtime_rate_regular'] : '1.50' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="overtime_rate_holiday" class="block text-sm font-medium text-gray-700 mb-1">Overtime Rate (Holiday)</label>
                    <input type="number" id="overtime_rate_holiday" name="overtime_rate_holiday" step="0.01" min="1" 
                           value="<?= $salary_info ? $salary_info['overtime_rate_holiday'] : '2.00' ?>" 
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
            </div>
            
            <!-- Summary and Preview Section -->
            <div class="border-t border-gray-200 mt-6 pt-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="text-md font-medium text-gray-700 mb-2">Salary Summary</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <table class="w-full text-sm">
                                <tr>
                                    <td class="py-1">Basic Salary:</td>
                                    <td class="py-1 text-right font-medium" id="summary_basic">Rs. 0.00</td>
                                </tr>
                                <tr>
                                    <td class="py-1">Total Allowances:</td>
                                    <td class="py-1 text-right font-medium" id="summary_allowances">Rs. 0.00</td>
                                </tr>
                                <tr>
                                    <td class="py-1">Gross Salary:</td>
                                    <td class="py-1 text-right font-medium" id="summary_gross">Rs. 0.00</td>
                                </tr>
                                <tr>
                                    <td class="py-1">EPF (Employee):</td>
                                    <td class="py-1 text-right font-medium text-red-600" id="summary_epf_employee">Rs. 0.00</td>
                                </tr>
                                <tr>
                                    <td class="py-1">EPF (Employer):</td>
                                    <td class="py-1 text-right font-medium text-green-600" id="summary_epf_employer">Rs. 0.00</td>
                                </tr>
                                <tr>
                                    <td class="py-1">ETF:</td>
                                    <td class="py-1 text-right font-medium text-blue-600" id="summary_etf">Rs. 0.00</td>
                                </tr>
                                <tr class="border-t border-gray-300">
                                    <td class="py-2 font-medium">Net Salary (Estimated):</td>
                                    <td class="py-2 text-right font-bold text-green-700" id="summary_net">Rs. 0.00</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="flex items-end justify-end">
                        <button type="submit" name="save_salary" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition">
                            <i class="fas fa-save mr-2"></i> Save Salary Settings
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="text-center py-6">
            <p class="text-gray-500">No employees found in the system. Please add employees first.</p>
            <a href="../staff_management/index.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-user-plus mr-2"></i> Manage Staff
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- If staff is selected, show salary history and loans -->
<?php if ($staff_id): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Salary History -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-lg font-semibold text-gray-800">Salary History</h2>
        </div>
        <div class="p-4">
            <?php
           
        
           // Initialize empty array for salary history
           $salary_history = [];
           $history_stmt = null;
           
           // Check if salary_records table exists
           $table_check = $conn->query("SHOW TABLES LIKE 'salary_records'");
           $table_exists = $table_check->num_rows > 0;
           
           if ($table_exists && $staff_id) {
               try {
                   // Original query
                   $history_query = "SELECT sr.*, DATE_FORMAT(sr.payment_date, '%d %b %Y') as formatted_date 
                                    FROM salary_records sr
                                    WHERE sr.staff_id = ?
                                    ORDER BY sr.pay_period DESC
                                    LIMIT 5";
                                    
                   $history_stmt = $conn->prepare($history_query);
                   if ($history_stmt) {
                       $history_stmt->bind_param("i", $staff_id);
                       $history_stmt->execute();
                       $history_result = $history_stmt->get_result();
                       
                       while ($row = $history_result->fetch_assoc()) {
                           $salary_history[] = $row;
                       }
                   }
               } catch (Exception $e) {
                   // Handle any errors gracefully
               }
           }
           
           // Make sure to close the statement if it was created
           if ($history_stmt) {
               try {
                   $history_stmt->close();
               } catch (Exception $e) {
                   // Ignore errors when closing
               }
           }
           ?>
           
           <div class="p-4">
               <?php if (count($salary_history) > 0): ?>
               <table class="min-w-full divide-y divide-gray-200">
                   <thead>
                       <tr>
                           <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Period</th>
                           <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                           <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                           <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                       </tr>
                   </thead>
                   <tbody class="bg-white divide-y divide-gray-200">
                       <?php foreach ($salary_history as $history): ?>
                       <tr>
                           <td class="px-4 py-2 whitespace-nowrap">
                               <div class="text-sm font-medium text-gray-900">
                                   <?= date('F Y', strtotime($history['pay_period'] . "-01")) ?>
                               </div>
                           </td>
                           <td class="px-4 py-2 whitespace-nowrap">
                               <div class="text-sm text-gray-900">Rs. <?= number_format($history['net_salary'], 2) ?></div>
                           </td>
                           <td class="px-4 py-2 whitespace-nowrap">
                               <?php
                               $status_class = '';
                               switch ($history['payment_status']) {
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
                               }
                               ?>
                               <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                   <?= $status_text ?>
                               </span>
                           </td>
                           <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                               <a href="payslip.php?staff_id=<?= $staff_id ?>&salary_id=<?= $history['salary_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                   <i class="fas fa-file-invoice-dollar"></i>
                               </a>
                           </td>
                       </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
               <?php else: ?>
               <p class="text-center text-gray-500 py-4">No salary records found for this employee.</p>
               <?php endif; ?>
           </div>
           

// Then in your HTML section, use the $salary_history array as before
// No else needed since $salary_history is initialized as an empty array
            $history_stmt->close();
            ?>
            
            <?php if (count($salary_history) > 0): ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Period</th>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($salary_history as $history): ?>
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= date('F Y', strtotime($history['pay_period'] . "-01")) ?>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Rs. <?= number_format($history['net_salary'], 2) ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <?php
                            $status_class = '';
                            switch ($history['payment_status']) {
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
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                            <a href="payslip.php?staff_id=<?= $staff_id ?>&salary_id=<?= $history['salary_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-center text-gray-500 py-4">No salary records found for this employee.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Loans and Advances -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Loans & Advances</h2>
            <a href="loans.php?staff_id=<?= $staff_id ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                Manage <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="p-4">
            <?php
            // Get active loans for this employee
            $loans = getEmployeeLoans($staff_id, 'active');
            ?>
            
            <?php if (count($loans) > 0): ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                        <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Deduction</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= ucfirst($loan['loan_type']) ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                Started: <?= date('d M, Y', strtotime($loan['start_date'])) ?>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Rs. <?= number_format($loan['loan_amount'], 2) ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Rs. <?= number_format($loan['remaining_amount'], 2) ?></div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                <?php $percent = ($loan['remaining_amount'] / $loan['loan_amount']) * 100; ?>
                                <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Rs. <?= number_format($loan['monthly_deduction'], 2) ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-center text-gray-500 py-4">No active loans or advances for this employee.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript for real-time calculations -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements for basic inputs
    const basicSalaryInput = document.getElementById('basic_salary');
    const transportAllowanceInput = document.getElementById('transport_allowance');
    const mealAllowanceInput = document.getElementById('meal_allowance');
    const housingAllowanceInput = document.getElementById('housing_allowance');
    const otherAllowanceInput = document.getElementById('other_allowance');
    const epfEmployeeInput = document.getElementById('epf_employee_percent');
    const epfEmployerInput = document.getElementById('epf_employer_percent');
    const etfInput = document.getElementById('etf_percent');
    
    // Summary elements
    const summaryBasic = document.getElementById('summary_basic');
    const summaryAllowances = document.getElementById('summary_allowances');
    const summaryGross = document.getElementById('summary_gross');
    const summaryEpfEmployee = document.getElementById('summary_epf_employee');
    const summaryEpfEmployer = document.getElementById('summary_epf_employer');
    const summaryEtf = document.getElementById('summary_etf');
    const summaryNet = document.getElementById('summary_net');
    
    // Function to update summary
    function updateSummary() {
        const basicSalary = parseFloat(basicSalaryInput.value) || 0;
        const transportAllowance = parseFloat(transportAllowanceInput.value) || 0;
        const mealAllowance = parseFloat(mealAllowanceInput.value) || 0;
        const housingAllowance = parseFloat(housingAllowanceInput.value) || 0;
        const otherAllowance = parseFloat(otherAllowanceInput.value) || 0;
        const epfEmployeePercent = parseFloat(epfEmployeeInput.value) || 0;
        const epfEmployerPercent = parseFloat(epfEmployerInput.value) || 0;
        const etfPercent = parseFloat(etfInput.value) || 0;
        
        // Calculate totals
        const totalAllowances = transportAllowance + mealAllowance + housingAllowance + otherAllowance;
        const grossSalary = basicSalary + totalAllowances;
        const epfEmployee = (basicSalary * epfEmployeePercent) / 100;
        const epfEmployer = (basicSalary * epfEmployerPercent) / 100;
        const etf = (basicSalary * etfPercent) / 100;
        const netSalary = grossSalary - epfEmployee;
        
        // Update summary display
        summaryBasic.textContent = 'Rs. ' + basicSalary.toFixed(2);
        summaryAllowances.textContent = 'Rs. ' + totalAllowances.toFixed(2);
        summaryGross.textContent = 'Rs. ' + grossSalary.toFixed(2);
        summaryEpfEmployee.textContent = 'Rs. ' + epfEmployee.toFixed(2);
        summaryEpfEmployer.textContent = 'Rs. ' + epfEmployer.toFixed(2);
        summaryEtf.textContent = 'Rs. ' + etf.toFixed(2);
        summaryNet.textContent = 'Rs. ' + netSalary.toFixed(2);
    }
    
    // Attach event listeners to inputs
    const allInputs = [
        basicSalaryInput, transportAllowanceInput, mealAllowanceInput, 
        housingAllowanceInput, otherAllowanceInput, epfEmployeeInput,
        epfEmployerInput, etfInput
    ];
    
    allInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', updateSummary);
        }
    });
    
    // Initial summary update
    updateSummary();
});
</script>

<?php
// Include footer
include_once('../../includes/footer.php');
?>