<?php
/**
 * EPF/ETF Report
 * 
 * This page generates reports for EPF (Employee Provident Fund) and 
 * ETF (Employee Trust Fund) contributions for a specific pay period.
 */

// Set page title
$page_title = "EPF/ETF Contribution Report";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Salary Management</a> / <span>EPF/ETF Report</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Check permissions
if (!has_permission('view_salary_reports')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p>You do not have permission to access this report.</p>
          </div>';
    include_once('../../includes/footer.php');
    exit;
}

// Get current year and month for default display
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Format for database query
$year_month = sprintf('%04d-%02d', $current_year, $current_month);

// Department filter
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

// Get report data
// Note: We're using the original getEpfEtfReportData function but applying department filtering in PHP
// since the original function doesn't support department filtering
$all_data = getEpfEtfReportData($year_month);

// Apply department filtering if needed
if (!empty($department_filter) && isset($all_data['records'])) {
    $filtered_records = array_filter($all_data['records'], function($record) use ($department_filter) {
        return isset($record['department']) && $record['department'] == $department_filter;
    });
    
    // Recalculate totals based on filtered records
    $totals = [
        'basic_salary' => 0,
        'epf_employee' => 0,
        'epf_employer' => 0,
        'etf' => 0,
        'total_epf' => 0
    ];
    
    foreach ($filtered_records as $record) {
        $totals['basic_salary'] += $record['basic_salary'];
        $totals['epf_employee'] += $record['epf_employee'];
        $totals['epf_employer'] += $record['epf_employer'];
        $totals['etf'] += $record['etf'];
        $totals['total_epf'] += $record['total_epf'];
    }
    
    $report_data = [
        'records' => $filtered_records,
        'totals' => $totals
    ];
} else {
    $report_data = $all_data;
}

// Get all departments for filter dropdown
function getAllDepartments() {
    global $conn;
    
    $query = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $result = $conn->query($query);
    
    $departments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    return $departments;
}

$departments = getAllDepartments();

// Function to format number with commas and 2 decimal places
function formatAmount($amount) {
    return number_format((float)$amount, 2, '.', ',');
}

// Generate month options for the dropdown
function generateMonthOptions($selected_month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    $options = '';
    foreach ($months as $num => $name) {
        $selected = ($num == $selected_month) ? 'selected' : '';
        $options .= "<option value=\"{$num}\" {$selected}>{$name}</option>";
    }
    
    return $options;
}

// Generate year options for the dropdown (last 5 years)
function generateYearOptions($selected_year) {
    $current_year = date('Y');
    $options = '';
    
    for ($year = $current_year; $year >= $current_year - 4; $year--) {
        $selected = ($year == $selected_year) ? 'selected' : '';
        $options .= "<option value=\"{$year}\" {$selected}>{$year}</option>";
    }
    
    return $options;
}
?>

<!-- Report Controls -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form action="" method="GET" class="md:flex md:items-end md:space-x-4">
        <div class="mb-4 md:mb-0">
            <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select id="month" name="month" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                <?= generateMonthOptions($current_month) ?>
            </select>
        </div>
        
        <div class="mb-4 md:mb-0">
            <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
            <select id="year" name="year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                <?= generateYearOptions($current_year) ?>
            </select>
        </div>
        
        <div class="mb-4 md:mb-0">
            <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select id="department" name="department" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['department']) ?>" <?= ($department_filter == $dept['department']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept['department']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="md:flex-shrink-0">
            <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md shadow-sm">
                Generate Report
            </button>
        </div>
    </form>
</div>

<!-- Report Title -->
<div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-bold text-gray-800">
        EPF/ETF Contribution Report - <?= date('F Y', strtotime($year_month . '-01')) ?>
        <?php if (!empty($department_filter)): ?>
            <span class="text-sm font-normal ml-2">(Department: <?= htmlspecialchars($department_filter) ?>)</span>
        <?php endif; ?>
    </h2>
    
    <div class="flex space-x-2">
        <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-1 px-3 rounded-md shadow-sm text-sm flex items-center">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        
        <button onclick="exportToExcel()" class="bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-md shadow-sm text-sm flex items-center">
            <i class="fas fa-file-excel mr-1"></i> Export to Excel
        </button>
    </div>
</div>

<!-- Report Content -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <!-- Table showing EPF/ETF data -->
        <table class="min-w-full divide-y divide-gray-200" id="epf-report-table">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Basic Salary (Rs.)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">EPF 8% (Rs.)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">EPF 12% (Rs.)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">EPF Total (Rs.)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ETF 3% (Rs.)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($report_data['records'])): ?>
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                        No records found for the selected period.
                    </td>
                </tr>
                <?php else: ?>
                    <?php $counter = 1; ?>
                    <?php foreach ($report_data['records'] as $record): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $counter++ ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($record['staff_code']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?= formatAmount($record['basic_salary']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?= formatAmount($record['epf_employee']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?= formatAmount($record['epf_employer']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?= formatAmount($record['total_epf']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?= formatAmount($record['etf']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <!-- Footer with totals -->
            <tfoot>
                <tr class="bg-gray-50">
                    <th colspan="3" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-900 uppercase tracking-wider"><?= formatAmount($report_data['totals']['basic_salary']) ?></th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-900 uppercase tracking-wider"><?= formatAmount($report_data['totals']['epf_employee']) ?></th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-900 uppercase tracking-wider"><?= formatAmount($report_data['totals']['epf_employer']) ?></th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-900 uppercase tracking-wider"><?= formatAmount($report_data['totals']['total_epf']) ?></th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-900 uppercase tracking-wider"><?= formatAmount($report_data['totals']['etf']) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Summary Information -->
<div class="mt-6 bg-white rounded-lg shadow-md p-4">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Summary for EPF Form C</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="border p-4 rounded-lg bg-blue-50">
            <h4 class="text-sm font-medium text-gray-500 mb-1">Total EPF Contribution</h4>
            <p class="text-2xl font-bold text-blue-600">Rs. <?= formatAmount($report_data['totals']['total_epf']) ?></p>
            <div class="mt-2 text-sm text-gray-600">
                <div>Employee (8%): Rs. <?= formatAmount($report_data['totals']['epf_employee']) ?></div>
                <div>Employer (12%): Rs. <?= formatAmount($report_data['totals']['epf_employer']) ?></div>
            </div>
        </div>
        
        <div class="border p-4 rounded-lg bg-green-50">
            <h4 class="text-sm font-medium text-gray-500 mb-1">Total ETF Contribution</h4>
            <p class="text-2xl font-bold text-green-600">Rs. <?= formatAmount($report_data['totals']['etf']) ?></p>
            <div class="mt-2 text-sm text-gray-600">
                <div>Employer (3%): Rs. <?= formatAmount($report_data['totals']['etf']) ?></div>
            </div>
        </div>
        
        <div class="border p-4 rounded-lg bg-purple-50">
            <h4 class="text-sm font-medium text-gray-500 mb-1">Total Employee Count</h4>
            <p class="text-2xl font-bold text-purple-600"><?= count($report_data['records']) ?></p>
            <div class="mt-2 text-sm text-gray-600">
                <div>Total Basic Salary: Rs. <?= formatAmount($report_data['totals']['basic_salary']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Print-specific styling -->
<style>
@media print {
    /* Hide non-printable elements */
    .main-header, .main-footer, #sidebar, #sidebar-overlay, button {
        display: none !important;
    }
    
    /* Full-width for content */
    #main-content-wrapper {
        margin: 0 !important;
        width: 100% !important;
    }
    
    /* Better table styling for print */
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    
    /* Background colors for print */
    thead tr, tfoot tr {
        background-color: #f2f2f2 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Add page title */
    body::before {
        content: "EPF/ETF Contribution Report - <?= date('F Y', strtotime($year_month . '-01')) ?>";
        display: block;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 20px;
    }
    
    /* Company name header */
    body::after {
        content: "<?= htmlspecialchars(get_setting('company_name', 'Company Name')) ?>";
        display: block;
        text-align: center;
        font-size: 14px;
        margin-bottom: 10px;
    }
}
</style>

<!-- JavaScript for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
<script>
function exportToExcel() {
    // Get the table
    var table = document.getElementById('epf-report-table');
    
    // Create a new workbook
    var wb = XLSX.utils.book_new();
    
    // Convert the table to worksheet
    var ws = XLSX.utils.table_to_sheet(table);
    
    // Generate filename with date
    var fileName = 'EPF_ETF_Report_<?= $year_month ?>.xlsx';
    
    // Write the workbook and trigger download
    XLSX.utils.book_append_sheet(wb, ws, 'EPF_ETF_Report');
    XLSX.writeFile(wb, fileName);
}
</script>

<?php
// Include footer
include_once('../../includes/footer.php');
?>