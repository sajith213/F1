<?php
/**
 * Attendance Reports
 * 
 * This file generates various reports related to staff attendance and overtime.
 */

// Set page title
$page_title = "Attendance Reports";

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
$report_type = isset($_GET['type']) ? $_GET['type'] : 'summary';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get staff selection from URL
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'summary';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'staff_id' => $staff_id
    ]);
    
    header("Location: attendance_reports.php?$query");
    exit;
}

// Function to get all staff for dropdown
function getAllStaff($conn) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position
            FROM 
                staff s
            WHERE 
                s.status = 'active'
            ORDER BY 
                s.last_name, s.first_name";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    return $staff;
}

// Function to get attendance summary data
function getAttendanceSummary($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(DISTINCT ar.attendance_date) as attendance_days,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                SUM(CASE WHEN ar.status = 'leave' THEN 1 ELSE 0 END) as leave_days,
                SUM(ar.hours_worked) as total_hours,
                COUNT(or1.overtime_id) as overtime_count,
                SUM(or1.overtime_hours) as overtime_hours
            FROM 
                staff s
                LEFT JOIN attendance_records ar ON s.staff_id = ar.staff_id 
                    AND ar.attendance_date BETWEEN ? AND ?
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

// Function to get detailed attendance records
function getDetailedAttendance($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                ar.attendance_id,
                ar.attendance_date,
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                ar.time_in,
                ar.time_out,
                ar.status,
                ar.hours_worked,
                ar.remarks,
                u.full_name as recorded_by,
                or1.overtime_hours,
                or1.overtime_rate,
                or1.overtime_amount,
                or1.status as overtime_status
            FROM 
                attendance_records ar
                JOIN staff s ON ar.staff_id = s.staff_id
                LEFT JOIN users u ON ar.recorded_by = u.user_id
                LEFT JOIN overtime_records or1 ON ar.attendance_id = or1.attendance_id
            WHERE 
                ar.attendance_date BETWEEN ? AND ?";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " ORDER BY ar.attendance_date DESC, s.last_name, s.first_name";
    
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

// Function to get overtime summary data
function getOvertimeSummary($conn, $start_date, $end_date, $staff_id = null) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(or1.overtime_id) as overtime_count,
                SUM(or1.overtime_hours) as overtime_hours,
                AVG(or1.overtime_rate) as average_rate,
                SUM(or1.overtime_amount) as overtime_amount,
                SUM(CASE WHEN or1.status = 'approved' THEN or1.overtime_hours ELSE 0 END) as approved_hours,
                SUM(CASE WHEN or1.status = 'rejected' THEN or1.overtime_hours ELSE 0 END) as rejected_hours,
                SUM(CASE WHEN or1.status = 'pending' THEN or1.overtime_hours ELSE 0 END) as pending_hours
            FROM 
                staff s
                LEFT JOIN attendance_records ar ON s.staff_id = ar.staff_id
                LEFT JOIN overtime_records or1 ON ar.attendance_id = or1.attendance_id
                    AND ar.attendance_date BETWEEN ? AND ?
            WHERE 
                s.status = 'active'";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " GROUP BY s.staff_id
              ORDER BY overtime_hours DESC";
    
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

// Function to get attendance status trends by date
function getAttendanceTrends($conn, $start_date, $end_date) {
    $sql = "SELECT 
                ar.attendance_date,
                COUNT(ar.attendance_id) as total_attendance,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count,
                SUM(CASE WHEN ar.status = 'leave' THEN 1 ELSE 0 END) as leave_count,
                SUM(ar.hours_worked) as total_hours,
                COUNT(or1.overtime_id) as overtime_count,
                SUM(or1.overtime_hours) as overtime_hours
            FROM 
                attendance_records ar
                LEFT JOIN overtime_records or1 ON ar.attendance_id = or1.attendance_id
            WHERE 
                ar.attendance_date BETWEEN ? AND ?
            GROUP BY 
                ar.attendance_date
            ORDER BY 
                ar.attendance_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Helper function to safely handle null values for array_sum
function safeSumColumn($data, $column) {
    return array_sum(array_map(function($row) use ($column) {
        return $row[$column] ?? 0;
    }, $data));
}

// Fetch all staff for dropdown
$all_staff = getAllStaff($conn);

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'detailed':
        $report_data = getDetailedAttendance($conn, $start_date, $end_date, $staff_id);
        $report_title = "Detailed Attendance Records";
        break;
        
    case 'overtime':
        $report_data = getOvertimeSummary($conn, $start_date, $end_date, $staff_id);
        $report_title = "Overtime Summary";
        break;
        
    case 'trends':
        $report_data = getAttendanceTrends($conn, $start_date, $end_date);
        $report_title = "Attendance Trends";
        break;
        
    case 'summary':
    default:
        $report_data = getAttendanceSummary($conn, $start_date, $end_date, $staff_id);
        $report_title = "Attendance Summary";
        break;
}

// Calculate totals for summaries
$summary_totals = [];

if ($report_type === 'summary') {
    $summary_totals = [
        'staff_count' => count($report_data),
        'attendance_days' => safeSumColumn($report_data, 'attendance_days'),
        'present_days' => safeSumColumn($report_data, 'present_days'),
        'absent_days' => safeSumColumn($report_data, 'absent_days'),
        'late_days' => safeSumColumn($report_data, 'late_days'),
        'half_days' => safeSumColumn($report_data, 'half_days'),
        'leave_days' => safeSumColumn($report_data, 'leave_days'),
        'total_hours' => safeSumColumn($report_data, 'total_hours'),
        'overtime_count' => safeSumColumn($report_data, 'overtime_count'),
        'overtime_hours' => safeSumColumn($report_data, 'overtime_hours')
    ];
} elseif ($report_type === 'overtime') {
    $summary_totals = [
        'staff_count' => count($report_data),
        'overtime_count' => safeSumColumn($report_data, 'overtime_count'),
        'overtime_hours' => safeSumColumn($report_data, 'overtime_hours'),
        'overtime_amount' => safeSumColumn($report_data, 'overtime_amount'),
        'approved_hours' => safeSumColumn($report_data, 'approved_hours'),
        'rejected_hours' => safeSumColumn($report_data, 'rejected_hours'),
        'pending_hours' => safeSumColumn($report_data, 'pending_hours')
    ];
} elseif ($report_type === 'trends') {
    $summary_totals = [
        'days_count' => count($report_data),
        'total_attendance' => safeSumColumn($report_data, 'total_attendance'),
        'present_count' => safeSumColumn($report_data, 'present_count'),
        'absent_count' => safeSumColumn($report_data, 'absent_count'),
        'late_count' => safeSumColumn($report_data, 'late_count'),
        'half_day_count' => safeSumColumn($report_data, 'half_day_count'),
        'leave_count' => safeSumColumn($report_data, 'leave_count'),
        'total_hours' => safeSumColumn($report_data, 'total_hours'),
        'overtime_count' => safeSumColumn($report_data, 'overtime_count'),
        'overtime_hours' => safeSumColumn($report_data, 'overtime_hours')
    ];
} elseif ($report_type === 'detailed') {
    $summary_totals = [
        'record_count' => count($report_data),
        'total_hours' => safeSumColumn($report_data, 'hours_worked'),
        'overtime_hours' => safeSumColumn($report_data, 'overtime_hours'),
        'status_counts' => [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'half_day' => 0,
            'leave' => 0
        ]
    ];
    
    // Count statuses
    foreach ($report_data as $row) {
        $status = $row['status'];
        if (isset($summary_totals['status_counts'][$status])) {
            $summary_totals['status_counts'][$status]++;
        }
    }
}

?>

<!-- Filter form -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="POST" action="" class="w-full">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="summary" <?= $report_type === 'summary' ? 'selected' : '' ?>>Attendance Summary</option>
                    <option value="detailed" <?= $report_type === 'detailed' ? 'selected' : '' ?>>Detailed Records</option>
                    <option value="overtime" <?= $report_type === 'overtime' ? 'selected' : '' ?>>Overtime Summary</option>
                    <option value="trends" <?= $report_type === 'trends' ? 'selected' : '' ?>>Attendance Trends</option>
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
            
            <div class="<?= $report_type === 'trends' ? 'hidden' : '' ?>" id="staff-filter-container">
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                <select id="staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): ?>
                        <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex items-end mt-4 md:mt-0">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php if ($report_type === 'summary'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Staff</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['staff_count'] ?? 0) ?></div>
            <div class="mt-2 text-xs text-gray-500">With attendance records</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Attendance Rate</div>
            <div class="text-2xl font-bold text-green-600">
                <?php
                $attendance_rate = 0;
                if (($summary_totals['attendance_days'] ?? 0) > 0) {
                    $attendance_rate = (($summary_totals['present_days'] ?? 0) / $summary_totals['attendance_days']) * 100;
                }
                echo number_format($attendance_rate, 2) . '%';
                ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Present days / Total days</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Hours</div>
            <div class="text-2xl font-bold text-blue-600"><?= number_format($summary_totals['total_hours'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Hours worked</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Overtime Hours</div>
            <div class="text-2xl font-bold text-indigo-600"><?= number_format($summary_totals['overtime_hours'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500"><?= number_format($summary_totals['overtime_count'] ?? 0) ?> overtime records</div>
        </div>
        
    <?php elseif ($report_type === 'overtime'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Staff</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['staff_count'] ?? 0) ?></div>
            <div class="mt-2 text-xs text-gray-500">With overtime records</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Overtime</div>
            <div class="text-2xl font-bold text-blue-600"><?= number_format($summary_totals['overtime_hours'] ?? 0, 2) ?> hrs</div>
            <div class="mt-2 text-xs text-gray-500"><?= number_format($summary_totals['overtime_count'] ?? 0) ?> overtime records</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Overtime Amount</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['overtime_amount'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Total overtime pay</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-medium text-green-600">Approved</div>
                <div class="text-lg font-bold text-green-600"><?= number_format($summary_totals['approved_hours'] ?? 0, 2) ?> hrs</div>
            </div>
            <div>
                <div class="text-xs font-medium text-yellow-600">Pending</div>
                <div class="text-lg font-bold text-yellow-600"><?= number_format($summary_totals['pending_hours'] ?? 0, 2) ?> hrs</div>
            </div>
            <div>
                <div class="text-xs font-medium text-red-600">Rejected</div>
                <div class="text-lg font-bold text-red-600"><?= number_format($summary_totals['rejected_hours'] ?? 0, 2) ?> hrs</div>
            </div>
        </div>
        
    <?php elseif ($report_type === 'trends'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Date Range</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['days_count'] ?? 0) ?> days</div>
            <div class="mt-2 text-xs text-gray-500">In selected period</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Attendance Rate</div>
            <div class="text-2xl font-bold text-green-600">
                <?php
                $attendance_rate = 0;
                if (($summary_totals['total_attendance'] ?? 0) > 0) {
                    $attendance_rate = (($summary_totals['present_count'] ?? 0) / $summary_totals['total_attendance']) * 100;
                }
                echo number_format($attendance_rate, 2) . '%';
                ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Present / Total attendance</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Hours</div>
            <div class="text-2xl font-bold text-blue-600"><?= number_format($summary_totals['total_hours'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Hours worked</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Overtime Hours</div>
            <div class="text-2xl font-bold text-indigo-600"><?= number_format($summary_totals['overtime_hours'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500"><?= number_format($summary_totals['overtime_count'] ?? 0) ?> overtime records</div>
        </div>
        
    <?php elseif ($report_type === 'detailed'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Records</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['record_count'] ?? 0) ?></div>
            <div class="mt-2 text-xs text-gray-500">Attendance records</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Hours</div>
            <div class="text-2xl font-bold text-blue-600"><?= number_format($summary_totals['total_hours'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Hours worked</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Overtime Hours</div>
            <div class="text-2xl font-bold text-indigo-600"><?= number_format($summary_totals['overtime_hours'] ?? 0, 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Additional hours</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="grid grid-cols-5 gap-2 text-center">
                <div>
                    <div class="text-xs font-medium text-green-600">Present</div>
                    <div class="text-base font-bold text-green-600"><?= number_format($summary_totals['status_counts']['present'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-red-600">Absent</div>
                    <div class="text-base font-bold text-red-600"><?= number_format($summary_totals['status_counts']['absent'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-yellow-600">Late</div>
                    <div class="text-base font-bold text-yellow-600"><?= number_format($summary_totals['status_counts']['late'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-orange-600">Half</div>
                    <div class="text-base font-bold text-orange-600"><?= number_format($summary_totals['status_counts']['half_day'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-blue-600">Leave</div>
                    <div class="text-base font-bold text-blue-600"><?= number_format($summary_totals['status_counts']['leave'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
    <?php elseif ($report_type === 'summary'): ?>
        <!-- Attendance Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Late</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Half Day</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Leave</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): 
                        $attendance_rate = ($row['attendance_days'] ?? 0) > 0 ? 
                                         (($row['present_days'] ?? 0) / $row['attendance_days']) * 100 : 0;
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
                                <?= number_format($row['attendance_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                <?= number_format($row['present_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                <?= number_format($row['absent_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-yellow-600">
                                <?= number_format($row['late_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-orange-600">
                                <?= number_format($row['half_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= number_format($row['leave_days'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_hours'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-indigo-600">
                                <?= number_format($row['overtime_hours'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?= $attendance_rate >= 90 ? 'bg-green-600' : ($attendance_rate >= 75 ? 'bg-yellow-500' : 'bg-red-600') ?> h-2.5 rounded-full" style="width: <?= $attendance_rate ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($attendance_rate, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Totals</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['attendance_days'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= number_format($summary_totals['present_days'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-red-600"><?= number_format($summary_totals['absent_days'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-yellow-600"><?= number_format($summary_totals['late_days'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-orange-600"><?= number_format($summary_totals['half_days'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600"><?= number_format($summary_totals['leave_days'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['total_hours'] ?? 0, 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-indigo-600"><?= number_format($summary_totals['overtime_hours'] ?? 0, 2) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900">
                            <?php
                            $overall_rate = ($summary_totals['attendance_days'] ?? 0) > 0 ? 
                                          (($summary_totals['present_days'] ?? 0) / $summary_totals['attendance_days']) * 100 : 0;
                            echo number_format($overall_rate, 2) . '%';
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'detailed'): ?>
        <!-- Detailed Attendance Records -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($row['attendance_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                <?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                <?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php
                                $status_classes = [
                                    'present' => 'bg-green-100 text-green-800',
                                    'absent' => 'bg-red-100 text-red-800',
                                    'late' => 'bg-yellow-100 text-yellow-800',
                                    'half_day' => 'bg-orange-100 text-orange-800',
                                    'leave' => 'bg-blue-100 text-blue-800'
                                ];
                                
                                $status_class = $status_classes[$row['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['hours_worked'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if (($row['overtime_hours'] ?? 0) > 0): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['overtime_status'] === 'approved' ? 'bg-green-100 text-green-800' : ($row['overtime_status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                        <?= number_format($row['overtime_hours'] ?? 0, 2) ?> hrs
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate">
                                <?= htmlspecialchars($row['remarks'] ?? '') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($report_type === 'overtime'): ?>
        <!-- Overtime Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">OT Records</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Hours</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Rate</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">OT Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
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
                                <?= number_format($row['overtime_count'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= number_format($row['overtime_hours'] ?? 0, 2) ?> hrs
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= number_format($row['average_rate'] ?? 0, 2) ?>x
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                <?= CURRENCY_SYMBOL . number_format($row['overtime_amount'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                <?= number_format($row['approved_hours'] ?? 0, 2) ?> hrs
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-yellow-600">
                                <?= number_format($row['pending_hours'] ?? 0, 2) ?> hrs
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                <?= number_format($row['rejected_hours'] ?? 0, 2) ?> hrs
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Totals</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['overtime_count'] ?? 0) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['overtime_hours'] ?? 0, 2) ?> hrs</td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600">
                            <?php
                            $avg_rate = 0;
                            if (($summary_totals['overtime_hours'] ?? 0) > 0) {
                                $avg_rate = safeSumColumn($report_data, 'overtime_amount') / $summary_totals['overtime_hours'];
                            }
                            echo number_format($avg_rate, 2);
                            ?>x
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['overtime_amount'] ?? 0, 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= number_format($summary_totals['approved_hours'] ?? 0, 2) ?> hrs</td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-yellow-600"><?= number_format($summary_totals['pending_hours'] ?? 0, 2) ?> hrs</td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-red-600"><?= number_format($summary_totals['rejected_hours'] ?? 0, 2) ?> hrs</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'trends'): ?>
        <!-- Attendance Trends Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Late</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Half Day</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Leave</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">OT Hours</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): 
                        $attendance_rate = ($row['total_attendance'] ?? 0) > 0 ? 
                                          (($row['present_count'] ?? 0) / $row['total_attendance']) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                <?= date('M d, Y', strtotime($row['attendance_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_attendance'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                <?= number_format($row['present_count'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                <?= number_format($row['absent_count'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-yellow-600">
                                <?= number_format($row['late_count'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-orange-600">
                                <?= number_format($row['half_day_count'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= number_format($row['leave_count'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_hours'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-indigo-600">
                                <?= number_format($row['overtime_hours'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?= $attendance_rate >= 90 ? 'bg-green-600' : ($attendance_rate >= 75 ? 'bg-yellow-500' : 'bg-red-600') ?> h-2.5 rounded-full" style="width: <?= $attendance_rate ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($attendance_rate, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">
                            <?php
                            $avg_attendance = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_attendance = ($summary_totals['total_attendance'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_attendance, 1);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600">
                            <?php
                            $avg_present = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_present = ($summary_totals['present_count'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_present, 1);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-red-600">
                            <?php
                            $avg_absent = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_absent = ($summary_totals['absent_count'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_absent, 1);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-yellow-600">
                            <?php
                            $avg_late = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_late = ($summary_totals['late_count'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_late, 1);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-orange-600">
                            <?php
                            $avg_half_day = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_half_day = ($summary_totals['half_day_count'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_half_day, 1);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600">
                            <?php
                            $avg_leave = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_leave = ($summary_totals['leave_count'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_leave, 1);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">
                            <?php
                            $avg_hours = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_hours = ($summary_totals['total_hours'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_hours, 2);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-indigo-600">
                            <?php
                            $avg_overtime = 0;
                            if (($summary_totals['days_count'] ?? 0) > 0) {
                                $avg_overtime = ($summary_totals['overtime_hours'] ?? 0) / $summary_totals['days_count'];
                            }
                            echo number_format($avg_overtime, 2);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900">
                            <?php
                            $overall_rate = ($summary_totals['total_attendance'] ?? 0) > 0 ? 
                                           (($summary_totals['present_count'] ?? 0) / $summary_totals['total_attendance']) * 100 : 0;
                            echo number_format($overall_rate, 2) . '%';
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for filter handling and export functionality -->
<script>
    // Toggle staff filter based on report type
    document.getElementById('report_type').addEventListener('change', function() {
        const reportType = this.value;
        const staffFilterContainer = document.getElementById('staff-filter-container');
        
        if (reportType === 'trends') {
            staffFilterContainer.classList.add('hidden');
        } else {
            staffFilterContainer.classList.remove('hidden');
        }
    });
    
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