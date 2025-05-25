<?php
/**
 * Staff Reports
 * 
 * This file generates various reports related to staff management.
 */

// Set page title
$page_title = "Staff Reports";

// Include necessary files
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user has permission
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../includes/footer.php';
    exit;
}

// Get report type from URL parameter
$report_type = isset($_GET['type']) ? $_GET['type'] : 'performance';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get staff selection from URL
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'performance';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $staff_id = $_POST['staff_id'] ?? null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'staff_id' => $staff_id
    ]);
    
    header("Location: staff_reports.php?$query");
    exit;
}

// Function to get all staff for selection dropdown
function getAllStaff($conn) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position
            FROM 
                staff s
            ORDER BY 
                s.last_name, s.first_name";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    return $staff;
}

// Function to get staff performance data
function getStaffPerformanceData($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(DISTINCT sl.sale_id) as total_sales,
                SUM(sl.net_amount) as sales_amount,
                AVG(sp.rating) as average_rating,
                COUNT(DISTINCT sa.assignment_id) as assignments
            FROM 
                staff s
                LEFT JOIN sales sl ON s.staff_id = sl.staff_id AND DATE(sl.sale_date) BETWEEN ? AND ?
                LEFT JOIN staff_performance sp ON s.staff_id = sp.staff_id AND sp.evaluation_date BETWEEN ? AND ?
                LEFT JOIN staff_assignments sa ON s.staff_id = sa.staff_id AND sa.assignment_date BETWEEN ? AND ?
            WHERE 
                s.status = 'active'";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " GROUP BY s.staff_id
              ORDER BY sales_amount DESC";
    
    if ($staff_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $staff_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get attendance summary by staff
function getAttendanceSummary($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(ar.attendance_id) as total_days,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                SUM(CASE WHEN ar.status = 'leave' THEN 1 ELSE 0 END) as leave_days,
                SUM(ar.hours_worked) as total_hours,
                SUM(CASE WHEN or1.overtime_id IS NOT NULL THEN or1.overtime_hours ELSE 0 END) as overtime_hours
            FROM 
                staff s
                LEFT JOIN attendance_records ar ON s.staff_id = ar.staff_id AND ar.attendance_date BETWEEN ? AND ?
                LEFT JOIN overtime_records or1 ON ar.attendance_id = or1.attendance_id
            WHERE 
                s.status = 'active'";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " GROUP BY s.staff_id
              ORDER BY s.last_name, s.first_name";
    
    if ($staff_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $start_date, $end_date, $staff_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get cash settlement data by staff
function getCashSettlementData($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(dcr.record_id) as total_settlements,
                SUM(dcr.expected_amount) as total_expected,
                SUM(dcr.collected_amount) as total_collected,
                SUM(dcr.difference) as total_difference,
                COUNT(CASE WHEN dcr.difference_type = 'excess' THEN 1 ELSE NULL END) as excess_count,
                COUNT(CASE WHEN dcr.difference_type = 'shortage' THEN 1 ELSE NULL END) as shortage_count,
                COUNT(CASE WHEN dcr.difference_type = 'balanced' THEN 1 ELSE NULL END) as balanced_count,
                SUM(CASE WHEN dcr.difference_type = 'excess' THEN dcr.difference ELSE 0 END) as excess_amount,
                SUM(CASE WHEN dcr.difference_type = 'shortage' THEN ABS(dcr.difference) ELSE 0 END) as shortage_amount
            FROM 
                staff s
                LEFT JOIN daily_cash_records dcr ON s.staff_id = dcr.staff_id AND dcr.record_date BETWEEN ? AND ?
            WHERE 
                s.status = 'active'";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " GROUP BY s.staff_id
              ORDER BY s.last_name, s.first_name";
    
    if ($staff_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $start_date, $end_date, $staff_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get staff assignment data
function getStaffAssignmentData($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(sa.assignment_id) as total_assignments,
                COUNT(DISTINCT sa.pump_id) as unique_pumps,
                COUNT(CASE WHEN sa.shift = 'morning' THEN 1 ELSE NULL END) as morning_shifts,
                COUNT(CASE WHEN sa.shift = 'afternoon' THEN 1 ELSE NULL END) as afternoon_shifts,
                COUNT(CASE WHEN sa.shift = 'evening' THEN 1 ELSE NULL END) as evening_shifts,
                COUNT(CASE WHEN sa.shift = 'night' THEN 1 ELSE NULL END) as night_shifts,
                COUNT(CASE WHEN sa.status = 'completed' THEN 1 ELSE NULL END) as completed_assignments,
                COUNT(CASE WHEN sa.status = 'absent' THEN 1 ELSE NULL END) as missed_assignments
            FROM 
                staff s
                LEFT JOIN staff_assignments sa ON s.staff_id = sa.staff_id AND sa.assignment_date BETWEEN ? AND ?
            WHERE 
                s.status = 'active'";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " GROUP BY s.staff_id
              ORDER BY total_assignments DESC";
    
    if ($staff_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $start_date, $end_date, $staff_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Fetch all staff for dropdown
$all_staff = getAllStaff($conn);

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'attendance':
        $report_data = getAttendanceSummary($conn, $start_date, $end_date, $staff_id);
        $report_title = $staff_id ? "Attendance Summary - Selected Staff" : "Attendance Summary - All Staff";
        break;
        
    case 'cash':
        $report_data = getCashSettlementData($conn, $start_date, $end_date, $staff_id);
        $report_title = $staff_id ? "Cash Settlement Summary - Selected Staff" : "Cash Settlement Summary - All Staff";
        break;
        
    case 'assignment':
        $report_data = getStaffAssignmentData($conn, $start_date, $end_date, $staff_id);
        $report_title = $staff_id ? "Pump Assignment Summary - Selected Staff" : "Pump Assignment Summary - All Staff";
        break;
        
    case 'performance':
    default:
        $report_data = getStaffPerformanceData($conn, $start_date, $end_date, $staff_id);
        $report_title = $staff_id ? "Staff Performance - Selected Staff" : "Staff Performance Overview";
        break;
}

?>

<!-- Filter form -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="POST" action="" class="w-full">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="performance" <?= $report_type === 'performance' ? 'selected' : '' ?>>Performance</option>
                    <option value="attendance" <?= $report_type === 'attendance' ? 'selected' : '' ?>>Attendance</option>
                    <option value="cash" <?= $report_type === 'cash' ? 'selected' : '' ?>>Cash Settlement</option>
                    <option value="assignment" <?= $report_type === 'assignment' ? 'selected' : '' ?>>Pump Assignments</option>
                </select>
            </div>
            
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Select Staff</label>
                <select id="staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): ?>
                        <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?> (<?= htmlspecialchars($staff['position']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Report header with export buttons -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0"><?= $report_title ?></h2>
        
        <div class="flex space-x-2">
            <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-print mr-2"></i> Print
            </button>
            <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-pdf mr-2"></i> PDF
            </button>
            <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-excel mr-2"></i> Excel
            </button>
        </div>
    </div>
</div>

<!-- Report content - changes based on report type -->
<div class="bg-white rounded-lg shadow-md overflow-hidden" id="report-content">
    <?php if (empty($report_data)): ?>
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
            <p>No data found for the selected criteria.</p>
        </div>
    <?php elseif ($report_type === 'performance'): ?>
        <!-- Staff Performance Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Sale</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Assignments</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $total_sales_amount = array_sum(array_column($report_data, 'sales_amount') ?: [0]);
                    
                    foreach ($report_data as $row): 
                        $avg_sale = $row['total_sales'] > 0 ? ($row['sales_amount'] ?? 0) / $row['total_sales'] : 0;
                        $performance_percentage = $total_sales_amount > 0 ? (($row['sales_amount'] ?? 0) / $total_sales_amount) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_sales'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['sales_amount'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($avg_sale ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <?php
                                    $rating = round($row['average_rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star text-yellow-400"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-gray-300"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($row['average_rating'] ?? 0, 1) ?>/5</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['assignments'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $performance_percentage ?? 0 ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($performance_percentage ?? 0, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'total_sales') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($total_sales_amount ?? 0, 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">
                            <?php
                            $total_transactions = array_sum(array_column($report_data, 'total_sales') ?: [0]);
                            $avg_total = $total_transactions > 0 ? $total_sales_amount / $total_transactions : 0;
                            echo CURRENCY_SYMBOL . number_format($avg_total ?? 0, 2);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">
                            <?php
                            $avg_rating = count($report_data) > 0 ? array_sum(array_column($report_data, 'average_rating') ?: [0]) / count($report_data) : 0;
                            echo number_format($avg_rating ?? 0, 1) . '/5';
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'assignments') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'attendance'): ?>
        <!-- Attendance Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Late</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hours Worked</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime Hours</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): 
                        $attendance_percentage = $row['total_days'] > 0 ? ($row['present_days'] / $row['total_days']) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['present_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['absent_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['late_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_hours'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['overtime_hours'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?= $attendance_percentage >= 90 ? 'bg-green-600' : ($attendance_percentage >= 75 ? 'bg-yellow-500' : 'bg-red-600') ?> h-2.5 rounded-full" style="width: <?= $attendance_percentage ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($attendance_percentage ?? 0, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= count($report_data) > 0 ? number_format(array_sum(array_column($report_data, 'total_days') ?: [0]) / count($report_data), 1) : '0.0' ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= count($report_data) > 0 ? number_format(array_sum(array_column($report_data, 'present_days') ?: [0]) / count($report_data), 1) : '0.0' ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= count($report_data) > 0 ? number_format(array_sum(array_column($report_data, 'absent_days') ?: [0]) / count($report_data), 1) : '0.0' ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= count($report_data) > 0 ? number_format(array_sum(array_column($report_data, 'late_days') ?: [0]) / count($report_data), 1) : '0.0' ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= count($report_data) > 0 ? number_format(array_sum(array_column($report_data, 'total_hours') ?: [0]) / count($report_data), 2) : '0.00' ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= count($report_data) > 0 ? number_format(array_sum(array_column($report_data, 'overtime_hours') ?: [0]) / count($report_data), 2) : '0.00' ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">
                            <?php
                            $avg_attendance = 0;
                            $total_days = array_sum(array_column($report_data, 'total_days') ?: [0]);
                            $total_present = array_sum(array_column($report_data, 'present_days') ?: [0]);
                            if ($total_days > 0) {
                                $avg_attendance = ($total_present / $total_days) * 100;
                            }
                            echo number_format($avg_attendance ?? 0, 2) . '%';
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'cash'): ?>
        <!-- Cash Settlement Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Settlements</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Collected Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Difference</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Excess Count</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Shortage Count</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Balanced Count</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_settlements'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['total_expected'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['total_collected'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium <?= ($row['total_difference'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= (($row['total_difference'] ?? 0) >= 0 ? '+' : '') . CURRENCY_SYMBOL . number_format($row['total_difference'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <?= number_format($row['excess_count'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    <?= number_format($row['shortage_count'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= number_format($row['balanced_count'] ?? 0) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'total_settlements') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format(array_sum(array_column($report_data, 'total_expected') ?: [0]), 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format(array_sum(array_column($report_data, 'total_collected') ?: [0]), 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium <?= array_sum(array_column($report_data, 'total_difference') ?: [0]) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= (array_sum(array_column($report_data, 'total_difference') ?: [0]) >= 0 ? '+' : '') . CURRENCY_SYMBOL . number_format(array_sum(array_column($report_data, 'total_difference') ?: [0]), 2) ?>
                        </td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'excess_count') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'shortage_count') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'balanced_count') ?: [0])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'assignment'): ?>
        <!-- Pump Assignment Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Assignments</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Pumps</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Morning</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Afternoon</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Evening</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Night</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Missed</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_assignments'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['unique_pumps'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['morning_shifts'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['afternoon_shifts'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['evening_shifts'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['night_shifts'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600 font-medium">
                                <?= number_format($row['completed_assignments'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600 font-medium">
                                <?= number_format($row['missed_assignments'] ?? 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'total_assignments') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">-</td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'morning_shifts') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'afternoon_shifts') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'evening_shifts') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'night_shifts') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= number_format(array_sum(array_column($report_data, 'completed_assignments') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-red-600"><?= number_format(array_sum(array_column($report_data, 'missed_assignments') ?: [0])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for export functionality -->
<script>
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library like TCPDF or FPDF
    }
    
    // Export to Excel
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require PhpSpreadsheet or similar library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>