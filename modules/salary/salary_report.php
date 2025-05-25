<?php
/**
 * Salary Report
 * 
 * This file generates various salary reports for management and accounting
 */

// Set page title
$page_title = "Salary Reports";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>Salary Reports</span>';

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
$start_year = isset($_GET['start_year']) ? intval($_GET['start_year']) : date('Y');
$start_month = isset($_GET['start_month']) ? intval($_GET['start_month']) : date('m');
$end_year = isset($_GET['end_year']) ? intval($_GET['end_year']) : date('Y');
$end_month = isset($_GET['end_month']) ? intval($_GET['end_month']) : date('m');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;

// Ensure end date is not before start date
if (($end_year < $start_year) || ($end_year == $start_year && $end_month < $start_month)) {
    $end_year = $start_year;
    $end_month = $start_month;
}

// Format date periods
$start_period = sprintf('%04d-%02d', $start_year, $start_month);
$end_period = sprintf('%04d-%02d', $end_year, $end_month);

// Get all departments for filter
$departments_query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Get all staff for individual report option
$all_staff_query = "SELECT staff_id, CONCAT(first_name, ' ', last_name, ' (', staff_code, ')') as employee_name FROM staff ORDER BY first_name, last_name";
$all_staff_result = $conn->query($all_staff_query);
$all_staff = [];
while ($row = $all_staff_result->fetch_assoc()) {
    $all_staff[$row['staff_id']] = $row['employee_name'];
}

// Build report data based on selected report type
$report_data = [];
$summary_data = [
    'total_employees' => 0,
    'total_gross' => 0,
    'total_net' => 0,
    'total_epf_employee' => 0,
    'total_epf_employer' => 0,
    'total_etf' => 0,
    'total_overtime' => 0
];

switch ($report_type) {
    case 'summary':
        // Summary report by month
        getSummaryReport();
        break;
        
    case 'department':
        // Department-wise salary report
        getDepartmentReport();
        break;
        
    case 'employee':
        // Individual employee salary history
        getEmployeeReport();
        break;
        
    case 'epf_etf':
        // EPF/ETF contributions report
        getEpfEtfReport();
        break;
        
    case 'detailed':
        // Detailed breakdown of all salaries
        getDetailedReport();
        break;
        
    default:
        // Default to summary
        getSummaryReport();
}

function getSummaryReport() {
    global $conn, $start_period, $end_period, $department, $report_data, $summary_data;
    
    $query = "SELECT 
              sr.pay_period,
              COUNT(DISTINCT sr.staff_id) AS employee_count,
              SUM(sr.gross_salary) AS total_gross,
              SUM(sr.net_salary) AS total_net,
              SUM(sr.epf_employee) AS total_epf_employee,
              SUM(sr.epf_employer) AS total_epf_employer,
              SUM(sr.etf) AS total_etf,
              SUM(sr.overtime_amount) AS total_overtime,
              COUNT(CASE WHEN sr.payment_status = 'paid' THEN 1 END) AS paid_count,
              COUNT(CASE WHEN sr.payment_status = 'pending' THEN 1 END) AS pending_count
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period BETWEEN ? AND ?";
    
    $params = [$start_period, $end_period];
    $param_types = "ss";
    
    if (!empty($department)) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $param_types .= "s";
    }
    
    $query .= " GROUP BY sr.pay_period ORDER BY sr.pay_period";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Format the period for display
        $period_parts = explode('-', $row['pay_period']);
        $period_display = date('F Y', mktime(0, 0, 0, $period_parts[1], 1, $period_parts[0]));
        
        $row['period_display'] = $period_display;
        $report_data[] = $row;
        
        // Update summary totals
        $summary_data['total_employees'] = max($summary_data['total_employees'], $row['employee_count']);
        $summary_data['total_gross'] += $row['total_gross'];
        $summary_data['total_net'] += $row['total_net'];
        $summary_data['total_epf_employee'] += $row['total_epf_employee'];
        $summary_data['total_epf_employer'] += $row['total_epf_employer'];
        $summary_data['total_etf'] += $row['total_etf'];
        $summary_data['total_overtime'] += $row['total_overtime'];
    }
    
    $stmt->close();
}

function getDepartmentReport() {
    global $conn, $start_period, $end_period, $department, $report_data, $summary_data;
    
    $query = "SELECT 
              s.department,
              COUNT(DISTINCT sr.staff_id) AS employee_count,
              SUM(sr.gross_salary) AS total_gross,
              SUM(sr.net_salary) AS total_net,
              SUM(sr.epf_employee) AS total_epf_employee,
              SUM(sr.epf_employer) AS total_epf_employer,
              SUM(sr.etf) AS total_etf,
              SUM(sr.overtime_amount) AS total_overtime
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period BETWEEN ? AND ?";
    
    $params = [$start_period, $end_period];
    $param_types = "ss";
    
    if (!empty($department)) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $param_types .= "s";
    }
    
    $query .= " GROUP BY s.department ORDER BY s.department";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        
        // Update summary totals
        $summary_data['total_employees'] += $row['employee_count'];
        $summary_data['total_gross'] += $row['total_gross'];
        $summary_data['total_net'] += $row['total_net'];
        $summary_data['total_epf_employee'] += $row['total_epf_employee'];
        $summary_data['total_epf_employer'] += $row['total_epf_employer'];
        $summary_data['total_etf'] += $row['total_etf'];
        $summary_data['total_overtime'] += $row['total_overtime'];
    }
    
    $stmt->close();
}

function getEmployeeReport() {
    global $conn, $start_period, $end_period, $staff_id, $department, $report_data, $summary_data;
    
    $query = "SELECT 
              sr.salary_id,
              sr.pay_period,
              sr.staff_id,
              CONCAT(s.first_name, ' ', s.last_name) AS employee_name,
              s.staff_code,
              s.department,
              s.position,
              sr.basic_salary,
              sr.gross_salary,
              sr.net_salary,
              sr.epf_employee,
              sr.epf_employer,
              sr.etf,
              sr.overtime_amount,
              sr.overtime_hours,
              sr.payment_status,
              sr.payment_date
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period BETWEEN ? AND ?";
    
    $params = [$start_period, $end_period];
    $param_types = "ss";
    
    if ($staff_id) {
        $query .= " AND sr.staff_id = ?";
        $params[] = $staff_id;
        $param_types .= "i";
    }
    
    if (!empty($department)) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $param_types .= "s";
    }
    
    $query .= " ORDER BY sr.pay_period DESC, employee_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Format the period for display
        $period_parts = explode('-', $row['pay_period']);
        $period_display = date('F Y', mktime(0, 0, 0, $period_parts[1], 1, $period_parts[0]));
        $row['period_display'] = $period_display;
        
        $report_data[] = $row;
        
        // Update summary totals
        $summary_data['total_gross'] += $row['gross_salary'];
        $summary_data['total_net'] += $row['net_salary'];
        $summary_data['total_epf_employee'] += $row['epf_employee'];
        $summary_data['total_epf_employer'] += $row['epf_employer'];
        $summary_data['total_etf'] += $row['etf'];
        $summary_data['total_overtime'] += $row['overtime_amount'];
    }
    
    $summary_data['total_employees'] = count(array_unique(array_column($report_data, 'staff_id')));
    
    $stmt->close();
}

function getEpfEtfReport() {
    global $conn, $start_period, $end_period, $department, $report_data, $summary_data;
    
    $query = "SELECT 
              sr.pay_period,
              CONCAT(s.first_name, ' ', s.last_name) AS employee_name,
              s.staff_code,
              sr.basic_salary,
              sr.epf_employee,
              sr.epf_employer,
              sr.etf
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period BETWEEN ? AND ?";
    
    $params = [$start_period, $end_period];
    $param_types = "ss";
    
    if (!empty($department)) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $param_types .= "s";
    }
    
    $query .= " ORDER BY sr.pay_period, employee_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Format the period for display
        $period_parts = explode('-', $row['pay_period']);
        $period_display = date('F Y', mktime(0, 0, 0, $period_parts[1], 1, $period_parts[0]));
        $row['period_display'] = $period_display;
        
        $report_data[] = $row;
        
        // Update summary totals
        $summary_data['total_epf_employee'] += $row['epf_employee'];
        $summary_data['total_epf_employer'] += $row['epf_employer'];
        $summary_data['total_etf'] += $row['etf'];
    }
    
    $summary_data['total_employees'] = count(array_unique(array_column($report_data, 'staff_code')));
    
    $stmt->close();
}

function getDetailedReport() {
    global $conn, $start_period, $end_period, $department, $report_data, $summary_data;
    
    $query = "SELECT 
              sr.*,
              CONCAT(s.first_name, ' ', s.last_name) AS employee_name,
              s.staff_code,
              s.department,
              s.position
              FROM salary_records sr
              JOIN staff s ON sr.staff_id = s.staff_id
              WHERE sr.pay_period BETWEEN ? AND ?";
    
    $params = [$start_period, $end_period];
    $param_types = "ss";
    
    if (!empty($department)) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $param_types .= "s";
    }
    
    $query .= " ORDER BY sr.pay_period, employee_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Format the period for display
        $period_parts = explode('-', $row['pay_period']);
        $period_display = date('F Y', mktime(0, 0, 0, $period_parts[1], 1, $period_parts[0]));
        $row['period_display'] = $period_display;
        
        $report_data[] = $row;
        
        // Update summary totals
        $summary_data['total_gross'] += $row['gross_salary'];
        $summary_data['total_net'] += $row['net_salary'];
        $summary_data['total_epf_employee'] += $row['epf_employee'];
        $summary_data['total_epf_employer'] += $row['epf_employer'];
        $summary_data['total_etf'] += $row['etf'];
        $summary_data['total_overtime'] += $row['overtime_amount'];
    }
    
    $summary_data['total_employees'] = count(array_unique(array_column($report_data, 'staff_id')));
    
    $stmt->close();
}

// Format date range for display
$date_range = '';
if ($start_year == $end_year && $start_month == $end_month) {
    // Single month
    $date_range = date('F Y', mktime(0, 0, 0, $start_month, 1, $start_year));
} else {
    // Date range
    $date_range = date('F Y', mktime(0, 0, 0, $start_month, 1, $start_year)) . ' to ' . 
                 date('F Y', mktime(0, 0, 0, $end_month, 1, $end_year));
}
?>

<!-- Main Content -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Salary Reports</h2>
    </div>
    
    <div class="p-4">
        <!-- Report Filters -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
            <form method="get" id="report-form" class="space-y-4">
                <div class="grid grid-cols-1 lg:grid-cols-6 gap-4">
                    <!-- Date Range -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <select name="start_month" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $start_month == $m ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="start_year" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 mt-2">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?= $y ?>" <?= $start_year == $y ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <select name="end_month" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $end_month == $m ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="end_year" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 mt-2">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?= $y ?>" <?= $end_year == $y ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Department Filter -->
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select id="department" name="department" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= $department == $dept ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Report Type -->
                    <div>
                        <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                        <select id="report_type" name="report_type" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="summary" <?= $report_type == 'summary' ? 'selected' : '' ?>>Monthly Summary</option>
                            <option value="department" <?= $report_type == 'department' ? 'selected' : '' ?>>Department Summary</option>
                            <option value="employee" <?= $report_type == 'employee' ? 'selected' : '' ?>>Employee Details</option>
                            <option value="epf_etf" <?= $report_type == 'epf_etf' ? 'selected' : '' ?>>EPF/ETF Report</option>
                            <option value="detailed" <?= $report_type == 'detailed' ? 'selected' : '' ?>>Detailed Report</option>
                        </select>
                    </div>
                    
                    <!-- Employee Filter (only visible for employee report) -->
                    <div id="employee-filter" class="<?= $report_type == 'employee' ? '' : 'hidden' ?>">
                        <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                        <select id="staff_id" name="staff_id" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">All Employees</option>
                            <?php foreach ($all_staff as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $staff_id == $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Generate Button -->
                    <div class="self-end">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-sync-alt mr-2"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Report Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div>
                <h3 class="text-xl font-bold text-gray-800">
                    <?php
                    $report_titles = [
                        'summary' => 'Monthly Salary Summary',
                        'department' => 'Department-wise Salary Summary',
                        'employee' => 'Employee Salary Details',
                        'epf_etf' => 'EPF/ETF Contributions Report',
                        'detailed' => 'Detailed Salary Report'
                    ];
                    echo $report_titles[$report_type] ?? 'Salary Report';
                    ?>
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    <?= $date_range ?>
                    <?= !empty($department) ? ' • Department: ' . htmlspecialchars($department) : '' ?>
                    <?= $staff_id && isset($all_staff[$staff_id]) ? ' • Employee: ' . htmlspecialchars($all_staff[$staff_id]) : '' ?>
                </p>
            </div>
            
            <!-- Export Options -->
            <div class="mt-4 md:mt-0 space-x-2">
                <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <button onclick="exportReport('csv')" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-file-csv mr-2"></i> CSV
                </button>
                <button onclick="exportReport('pdf')" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-file-pdf mr-2"></i> PDF
                </button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Employees -->
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <p class="text-sm text-gray-600">Total Employees</p>
                <p class="text-xl font-bold text-blue-800"><?= $summary_data['total_employees'] ?></p>
            </div>
            
            <!-- Total Gross Salary -->
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-sm text-gray-600">Total Gross Salary</p>
                <p class="text-xl font-bold text-green-800">Rs. <?= number_format($summary_data['total_gross'], 2) ?></p>
            </div>
            
            <!-- Total EPF/ETF -->
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <p class="text-sm text-gray-600">Total EPF/ETF</p>
                <p class="text-xl font-bold text-purple-800">Rs. <?= number_format($summary_data['total_epf_employee'] + $summary_data['total_epf_employer'] + $summary_data['total_etf'], 2) ?></p>
                <p class="text-xs text-gray-500 mt-1">Employee: <?= number_format($summary_data['total_epf_employee'], 2) ?> | Employer: <?= number_format($summary_data['total_epf_employer'] + $summary_data['total_etf'], 2) ?></p>
            </div>
            
            <!-- Total Net Salary -->
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <p class="text-sm text-gray-600">Total Net Salary</p>
                <p class="text-xl font-bold text-yellow-800">Rs. <?= number_format($summary_data['total_net'], 2) ?></p>
            </div>
        </div>
        
        <!-- Report Content -->
        <div id="report-content" class="overflow-x-auto border rounded-lg">
            <?php if (empty($report_data)): ?>
            <div class="text-center py-10">
                <p class="text-gray-500">No data found for the selected criteria.</p>
            </div>
            <?php else: ?>
                <?php if ($report_type == 'summary'): ?>
                <!-- Monthly Summary Report -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employees</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF (Employee)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF (Employer)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ETF</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= $row['period_display'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $row['employee_count'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_gross'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_epf_employee'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_epf_employer'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_etf'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                Rs. <?= number_format($row['total_net'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $row['paid_count'] ?> Paid / <?= $row['pending_count'] ?> Pending
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type == 'department'): ?>
                <!-- Department Summary Report -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employees</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF (Employee)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF (Employer)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ETF</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($row['department'] ?? 'Not Assigned') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $row['employee_count'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_gross'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_epf_employee'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_epf_employer'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_etf'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                Rs. <?= number_format($row['total_net'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_overtime'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type == 'employee'): ?>
                <!-- Employee Details Report -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basic Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $row['period_display'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['employee_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['department'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['basic_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['gross_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Rs. <?= number_format($row['net_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= number_format($row['overtime_hours'], 1) ?> hrs (Rs. <?= number_format($row['overtime_amount'], 2) ?>)
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_class = '';
                                switch ($row['payment_status']) {
                                    case 'paid':
                                        $status_class = 'bg-green-100 text-green-800';
                                        break;
                                    case 'pending':
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'cancelled':
                                        $status_class = 'bg-red-100 text-red-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                    <?= ucfirst($row['payment_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="payslip.php?salary_id=<?= $row['salary_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type == 'epf_etf'): ?>
                <!-- EPF/ETF Report -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basic Salary</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF No.</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF (Employee)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPF (Employer)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ETF</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Contribution</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $row['period_display'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['employee_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['basic_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['staff_code']) ?> <!-- Assuming staff code is used as EPF number -->
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['epf_employee'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['epf_employer'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['etf'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Rs. <?= number_format($row['epf_employee'] + $row['epf_employer'] + $row['etf'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php elseif ($report_type == 'detailed'): ?>
                <!-- Detailed Report -->
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basic</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Allowances</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deductions</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $row['period_display'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['employee_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['basic_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['transport_allowance'] + $row['meal_allowance'] + $row['housing_allowance'] + $row['other_allowance'], 2) ?>
                                <div class="text-xs text-gray-400">
                                    <?php if ($row['transport_allowance'] > 0): ?>
                                    T: <?= number_format($row['transport_allowance'], 2) ?> | 
                                    <?php endif; ?>
                                    <?php if ($row['meal_allowance'] > 0): ?>
                                    M: <?= number_format($row['meal_allowance'], 2) ?> | 
                                    <?php endif; ?>
                                    <?php if ($row['housing_allowance'] > 0): ?>
                                    H: <?= number_format($row['housing_allowance'], 2) ?> | 
                                    <?php endif; ?>
                                    <?php if ($row['other_allowance'] > 0): ?>
                                    O: <?= number_format($row['other_allowance'], 2) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= number_format($row['overtime_hours'], 1) ?> hrs
                                <div class="text-xs text-gray-400">Rs. <?= number_format($row['overtime_amount'], 2) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['gross_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Rs. <?= number_format($row['total_deductions'], 2) ?>
                                <div class="text-xs text-gray-400">
                                    EPF: <?= number_format($row['epf_employee'], 2) ?> | 
                                    <?php if ($row['loan_deductions'] > 0): ?>
                                    Loan: <?= number_format($row['loan_deductions'], 2) ?> | 
                                    <?php endif; ?>
                                    <?php if ($row['other_deductions'] > 0): ?>
                                    Other: <?= number_format($row['other_deductions'], 2) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Rs. <?= number_format($row['net_salary'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_class = '';
                                switch ($row['payment_status']) {
                                    case 'paid':
                                        $status_class = 'bg-green-100 text-green-800';
                                        break;
                                    case 'pending':
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'cancelled':
                                        $status_class = 'bg-red-100 text-red-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                    <?= ucfirst($row['payment_status']) ?>
                                </span>
                                <?php if ($row['payment_status'] == 'paid' && $row['payment_date']): ?>
                                <div class="text-xs text-gray-500 mt-1"><?= date('d M Y', strtotime($row['payment_date'])) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript for report functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide employee filter based on report type
    const reportTypeSelect = document.getElementById('report_type');
    const employeeFilter = document.getElementById('employee-filter');
    
    if (reportTypeSelect && employeeFilter) {
        reportTypeSelect.addEventListener('change', function() {
            if (this.value === 'employee') {
                employeeFilter.classList.remove('hidden');
            } else {
                employeeFilter.classList.add('hidden');
            }
        });
    }
});

// Print report
function printReport() {
    const printContents = document.getElementById('report-content').innerHTML;
    const originalContents = document.body.innerHTML;
    
    // Get report title and date range
    const reportTitle = document.querySelector('h3.text-xl').innerText;
    const dateRange = document.querySelector('p.text-sm.text-gray-600').innerText;
    
    // Create print page with styling
    const printPage = `
        <html>
        <head>
            <title>${reportTitle}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { font-size: 18px; margin-bottom: 5px; }
                p { font-size: 12px; margin-top: 0; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #f3f4f6; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
                td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
                .summary { margin: 20px 0; display: flex; justify-content: space-between; }
                .summary-card { padding: 10px; border: 1px solid #e5e7eb; border-radius: 5px; width: 23%; }
                .summary-title { font-size: 12px; color: #6b7280; margin-bottom: 5px; }
                .summary-value { font-size: 16px; font-weight: bold; }
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <h1>${reportTitle}</h1>
            <p>${dateRange}</p>
            
            <div class="summary">
                <div class="summary-card">
                    <div class="summary-title">Total Employees</div>
                    <div class="summary-value">${document.querySelector('.bg-blue-50 .text-xl').innerText}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-title">Total Gross Salary</div>
                    <div class="summary-value">${document.querySelector('.bg-green-50 .text-xl').innerText}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-title">Total EPF/ETF</div>
                    <div class="summary-value">${document.querySelector('.bg-purple-50 .text-xl').innerText}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-title">Total Net Salary</div>
                    <div class="summary-value">${document.querySelector('.bg-yellow-50 .text-xl').innerText}</div>
                </div>
            </div>
            
            ${printContents}
        </body>
        </html>
    `;
    
    document.body.innerHTML = printPage;
    window.print();
    document.body.innerHTML = originalContents;
}

// Export report
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('format', format);
    params.append('export', '1');
    
    window.location.href = 'export_report.php?' + params.toString();
}
</script>

<?php
// Include footer
include_once('../../includes/footer.php');
?>