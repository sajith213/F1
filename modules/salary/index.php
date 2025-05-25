<?php
/**
 * Salary Module - Main Dashboard
 * 
 * This file displays the salary management dashboard with key metrics and actions
 */

// Set page title
$page_title = "Salary Management";
$breadcrumbs = '<a href="../../index.php">Home</a> / <span>Salary Management</span>';

// Include header
include_once('../../includes/header.php');

// Include auth.php for has_permission function
require_once('../../includes/auth.php');

// Include module functions
require_once('functions.php');

// Check permissions
if (!has_permission('manage_salaries')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Rest of your code remains the same...

// Get current month and year
$current_month = date('m');
$current_year = date('Y');
$month_name = date('F');

// Get summary statistics
$total_employees = count(getAllEmployeesWithSalaryInfo());
$total_salary_budget = calculateTotalSalaryBudget();
$pending_payments = getCountOfPendingSalaries($current_year, $current_month);
$completed_payments = getCountOfCompletedSalaries($current_year, $current_month);
?>

<!-- Dashboard Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Employees Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Total Employees</h3>
                <p class="text-2xl font-bold text-blue-600"><?= $total_employees ?></p>
            </div>
            <div class="rounded-full bg-blue-100 p-2">
                <i class="fas fa-users text-blue-500"></i>
            </div>
        </div>
    </div>

    <!-- Monthly Budget Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Monthly Salary Budget</h3>
                <p class="text-2xl font-bold text-green-600">Rs. <?= number_format($total_salary_budget, 2) ?></p>
            </div>
            <div class="rounded-full bg-green-100 p-2">
                <i class="fas fa-money-bill-wave text-green-500"></i>
            </div>
        </div>
    </div>

    <!-- Payments Pending Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Payments Pending</h3>
                <p class="text-2xl font-bold text-orange-600"><?= $pending_payments ?></p>
            </div>
            <div class="rounded-full bg-orange-100 p-2">
                <i class="fas fa-clock text-orange-500"></i>
            </div>
        </div>
    </div>

    <!-- Payments Completed Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Payments Completed</h3>
                <p class="text-2xl font-bold text-purple-600"><?= $completed_payments ?></p>
            </div>
            <div class="rounded-full bg-purple-100 p-2">
                <i class="fas fa-check-circle text-purple-500"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="flex flex-wrap justify-between mb-6 gap-2">
    <div class="flex flex-wrap gap-2">
        <a href="employee_salary.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-user-cog mr-2"></i> Employee Salary Settings
        </a>
        <a href="salary_calculator.php" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-calculator mr-2"></i> Calculate Salaries
        </a>
        <a href="loans.php" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-hand-holding-usd mr-2"></i> Loans & Advances
        </a>
    </div>
    
    <div>
        <a href="salary_report.php" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-chart-bar mr-2"></i> Reports
        </a>
    </div>
</div>

<!-- Recent Salary Periods -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Recent Salary Periods</h2>
    </div>
    
    <div class="p-4 overflow-x-auto">
        <!-- Table showing recent salary periods with stats -->
        <?php
        // Example: List of last 3 months
        $months = [];
        for ($i = 0; $i < 3; $i++) {
            $month = date('m', strtotime("-$i months"));
            $year = date('Y', strtotime("-$i months"));
            $months[] = [
                'period' => date('F Y', strtotime("$year-$month-01")),
                'year' => $year,
                'month' => $month,
                'total' => getSalaryTotalForPeriod($year, $month),
                'count' => getSalaryCountForPeriod($year, $month),
                'status' => getSalaryPeriodStatus($year, $month)
            ];
        }
        ?>
        
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Period</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employees</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($months as $month): ?>
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?= $month['period'] ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900">Rs. <?= number_format($month['total'], 2) ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= $month['count'] ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <?php
                        $status_class = '';
                        switch ($month['status']) {
                            case 'completed':
                                $status_class = 'bg-green-100 text-green-800';
                                break;
                            case 'in_progress':
                                $status_class = 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'pending':
                                $status_class = 'bg-red-100 text-red-800';
                                break;
                        }
                        ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                            <?= ucfirst($month['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                        <a href="view_salaries.php?year=<?= $month['year'] ?>&month=<?= $month['month'] ?>" class="text-blue-600 hover:text-blue-900 mr-2">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="process_salaries.php?year=<?= $month['year'] ?>&month=<?= $month['month'] ?>" class="text-green-600 hover:text-green-900 mr-2">
                            <i class="fas fa-cogs"></i>
                        </a>
                        <a href="salary_report.php?year=<?= $month['year'] ?>&month=<?= $month['month'] ?>" class="text-purple-600 hover:text-purple-900">
                            <i class="fas fa-file-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Employee Salary Overview -->
<div class="bg-white rounded-lg shadow-md">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Employee Salary Overview</h2>
        <a href="employee_salary.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            Manage All <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <div class="p-4 overflow-x-auto">
        <?php
        // Get employees with salary info
        $employees = getAllEmployeesWithSalaryInfo();
        ?>
        
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basic Salary</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($employees as $employee): ?>
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex items-center">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                </div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($employee['staff_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($employee['position']) ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            <?= isset($employee['basic_salary']) ? 'Rs. ' . number_format($employee['basic_salary'], 2) : 'Not Set' ?>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            <?= isset($employee['effective_date']) ? date('d M, Y', strtotime($employee['effective_date'])) : '-' ?>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                        <a href="employee_salary.php?staff_id=<?= $employee['staff_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="payslip.php?staff_id=<?= $employee['staff_id'] ?>&year=<?= $current_year ?>&month=<?= $current_month ?>" class="text-green-600 hover:text-green-900">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Include footer
include_once('../../includes/footer.php');
?>