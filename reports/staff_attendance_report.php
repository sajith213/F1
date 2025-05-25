<?php
/**
 * Staff Attendance & Dispensing Report
 * 
 * A simplified report to evaluate staff performance and work hours based on attendance and fuel dispensed.
 */
ob_start();
// Set page title
$page_title = "Staff Attendance & Dispensing Report";

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

// Set default filter values
$period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'monthly';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $period_type = $_POST['period_type'] ?? 'monthly';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'period_type' => $period_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'staff_id' => $staff_id
    ]);
    
    header("Location: staff_attendance_report.php?$query");
    exit;
}

// Fetch staff for dropdown
function getAllStaff($conn) {
    $sql = "SELECT 
                staff_id,
                CONCAT(first_name, ' ', last_name) AS staff_name,
                staff_code,
                position
            FROM 
                staff
            WHERE 
                status = 'active'
            ORDER BY 
                last_name, first_name";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    return $staff;
}

// Get attendance data for staff
function getStaffAttendanceData($conn, $start_date, $end_date, $staff_id = null, $period_type = 'monthly') {
    // Set the grouping field based on period type
    $group_format = '';
    $date_format = '';
    
    switch ($period_type) {
        case 'weekly':
            $group_format = "CONCAT(YEAR(ar.attendance_date), '-', WEEK(ar.attendance_date, 1))";
            $date_format = "STR_TO_DATE(CONCAT(YEARWEEK(ar.attendance_date, 1), ' Monday'), '%X%V %W')";
            break;
        case 'monthly':
            $group_format = "DATE_FORMAT(ar.attendance_date, '%Y-%m')";
            $date_format = "DATE_FORMAT(ar.attendance_date, '%Y-%m-01')";
            break;
        case 'daily':
        default:
            $group_format = "ar.attendance_date";
            $date_format = "ar.attendance_date";
            break;
    }
    
    // Build the query
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                s.position,
                $group_format AS period,
                MIN($date_format) AS period_date,
                COUNT(ar.attendance_id) AS total_days,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) AS late_days,
                SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) AS half_days,
                SUM(CASE WHEN ar.status = 'leave' THEN 1 ELSE 0 END) AS leave_days,
                SUM(ar.hours_worked) AS total_hours,
                SUM(CASE WHEN or1.overtime_id IS NOT NULL THEN or1.overtime_hours ELSE 0 END) AS overtime_hours
            FROM 
                staff s
                LEFT JOIN attendance_records ar ON s.staff_id = ar.staff_id 
                    AND ar.attendance_date BETWEEN ? AND ?
                LEFT JOIN overtime_records or1 ON ar.attendance_id = or1.attendance_id
            WHERE 
                s.status = 'active'";
    
    $params = [$start_date, $end_date];
    $types = "ss";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
        $params[] = $staff_id;
        $types .= "i";
    }
    
    $sql .= " GROUP BY 
                s.staff_id, period
              ORDER BY 
                s.last_name, s.first_name, period_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Get fuel dispensed data by staff based on their assignments
function getStaffDispensingData($conn, $start_date, $end_date, $staff_id = null, $period_type = 'monthly') {
    // Set the grouping field based on period type
    $group_format = '';
    $date_format = '';
    
    switch ($period_type) {
        case 'weekly':
            $group_format = "CONCAT(YEAR(mr.reading_date), '-', WEEK(mr.reading_date, 1))";
            $date_format = "STR_TO_DATE(CONCAT(YEARWEEK(mr.reading_date, 1), ' Monday'), '%X%V %W')";
            break;
        case 'monthly':
            $group_format = "DATE_FORMAT(mr.reading_date, '%Y-%m')";
            $date_format = "DATE_FORMAT(mr.reading_date, '%Y-%m-01')";
            break;
        case 'daily':
        default:
            $group_format = "mr.reading_date";
            $date_format = "mr.reading_date";
            break;
    }
    
    // Build the query that connects meter readings to staff assignments
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                s.position,
                $group_format AS period,
                MIN($date_format) AS period_date,
                SUM(mr.volume_dispensed) AS volume_dispensed,
                COUNT(DISTINCT mr.reading_date) AS work_days,
                COUNT(DISTINCT pn.nozzle_id) AS nozzles_operated
            FROM 
                staff s
                JOIN staff_assignments sa ON s.staff_id = sa.staff_id
                JOIN pumps p ON sa.pump_id = p.pump_id
                JOIN pump_nozzles pn ON p.pump_id = pn.pump_id
                JOIN meter_readings mr ON pn.nozzle_id = mr.nozzle_id
                    AND DATE(mr.reading_date) = sa.assignment_date
            WHERE 
                s.status = 'active'
                AND sa.assignment_date BETWEEN ? AND ?
                AND sa.status IN ('assigned', 'completed')";
    
    $params = [$start_date, $end_date];
    $types = "ss";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
        $params[] = $staff_id;
        $types .= "i";
    }
    
    $sql .= " GROUP BY 
                s.staff_id, period
              ORDER BY 
                s.last_name, s.first_name, period_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Combine attendance and dispensing data for unified reporting
function combineStaffData($attendance_data, $dispensing_data) {
    $combined_data = [];
    
    // First, process all attendance data
    foreach ($attendance_data as $attendance) {
        $key = $attendance['staff_id'] . '_' . $attendance['period'];
        
        if (!isset($combined_data[$key])) {
            $combined_data[$key] = [
                'staff_id' => $attendance['staff_id'],
                'staff_code' => $attendance['staff_code'],
                'staff_name' => $attendance['staff_name'],
                'position' => $attendance['position'],
                'period' => $attendance['period'],
                'period_date' => $attendance['period_date'],
                // Attendance data
                'total_days' => $attendance['total_days'],
                'present_days' => $attendance['present_days'],
                'absent_days' => $attendance['absent_days'],
                'late_days' => $attendance['late_days'],
                'half_days' => $attendance['half_days'],
                'leave_days' => $attendance['leave_days'],
                'total_hours' => $attendance['total_hours'],
                'overtime_hours' => $attendance['overtime_hours'],
                // Placeholder for dispensing data
                'volume_dispensed' => 0,
                'work_days' => 0,
                'nozzles_operated' => 0
            ];
        }
    }
    
    // Then, merge in dispensing data
    foreach ($dispensing_data as $dispensing) {
        $key = $dispensing['staff_id'] . '_' . $dispensing['period'];
        
        if (isset($combined_data[$key])) {
            // Update existing record
            $combined_data[$key]['volume_dispensed'] = $dispensing['volume_dispensed'];
            $combined_data[$key]['work_days'] = $dispensing['work_days'];
            $combined_data[$key]['nozzles_operated'] = $dispensing['nozzles_operated'];
        } else {
            // Create new record if it doesn't exist in attendance data
            $combined_data[$key] = [
                'staff_id' => $dispensing['staff_id'],
                'staff_code' => $dispensing['staff_code'],
                'staff_name' => $dispensing['staff_name'],
                'position' => $dispensing['position'],
                'period' => $dispensing['period'],
                'period_date' => $dispensing['period_date'],
                // Placeholder for attendance data
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'late_days' => 0, 
                'half_days' => 0,
                'leave_days' => 0,
                'total_hours' => 0,
                'overtime_hours' => 0,
                // Dispensing data
                'volume_dispensed' => $dispensing['volume_dispensed'],
                'work_days' => $dispensing['work_days'],
                'nozzles_operated' => $dispensing['nozzles_operated']
            ];
        }
    }
    
    // Convert associative array to indexed array and calculate averages
    $result = [];
    foreach ($combined_data as $data) {
        // Calculate average liters per day
        if ($data['present_days'] > 0) {
            $data['avg_liters_per_day'] = $data['volume_dispensed'] / $data['present_days'];
        } else {
            $data['avg_liters_per_day'] = 0;
        }
        
        $result[] = $data;
    }
    
    // Sort by staff name and period
    usort($result, function($a, $b) {
        $name_compare = strcmp($a['staff_name'], $b['staff_name']);
        if ($name_compare !== 0) {
            return $name_compare;
        }
        return strcmp($a['period_date'], $b['period_date']);
    });
    
    return $result;
}

// Calculate staff summary statistics grouped by staff
function calculateStaffSummary($combined_data) {
    $summary = [];
    
    foreach ($combined_data as $data) {
        $staff_id = $data['staff_id'];
        
        if (!isset($summary[$staff_id])) {
            $summary[$staff_id] = [
                'staff_id' => $data['staff_id'],
                'staff_code' => $data['staff_code'],
                'staff_name' => $data['staff_name'],
                'position' => $data['position'],
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'total_hours' => 0,
                'overtime_hours' => 0,
                'volume_dispensed' => 0,
                'periods_count' => 0,
                'attendance_percentage' => 0
            ];
        }
        
        $summary[$staff_id]['total_days'] += $data['total_days'];
        $summary[$staff_id]['present_days'] += $data['present_days'];
        $summary[$staff_id]['absent_days'] += $data['absent_days'];
        $summary[$staff_id]['total_hours'] += $data['total_hours'];
        $summary[$staff_id]['overtime_hours'] += $data['overtime_hours'];
        $summary[$staff_id]['volume_dispensed'] += $data['volume_dispensed'];
        $summary[$staff_id]['periods_count']++;
    }
    
    // Calculate averages and percentages
    foreach ($summary as &$staff) {
        // Calculate attendance percentage
        if ($staff['total_days'] > 0) {
            $staff['attendance_percentage'] = ($staff['present_days'] / $staff['total_days']) * 100;
        }
        
        // Calculate average liters per day
        if ($staff['present_days'] > 0) {
            $staff['avg_liters_per_day'] = $staff['volume_dispensed'] / $staff['present_days'];
        } else {
            $staff['avg_liters_per_day'] = 0;
        }
    }
    
    // Convert to indexed array
    $result = array_values($summary);
    
    // Sort by dispensed volume in descending order
    usort($result, function($a, $b) {
        return $b['volume_dispensed'] - $a['volume_dispensed'];
    });
    
    return $result;
}

// Format period label based on period type
function formatPeriodLabel($period_date, $period_type) {
    // Check for null or empty date
    if (empty($period_date)) {
        return "Unknown Period";
    }
    
    switch ($period_type) {
        case 'weekly':
            $date = new DateTime($period_date);
            $week = $date->format('W');
            $year = $date->format('Y');
            return "Week $week, $year";
        case 'monthly':
            return date('F Y', strtotime($period_date));
        case 'daily':
        default:
            return date('M d, Y', strtotime($period_date));
    }
}

// Get data for the report
$staff_list = getAllStaff($conn);
$attendance_data = getStaffAttendanceData($conn, $start_date, $end_date, $staff_id, $period_type);
$dispensing_data = getStaffDispensingData($conn, $start_date, $end_date, $staff_id, $period_type);
$combined_data = combineStaffData($attendance_data, $dispensing_data);
$staff_summary = calculateStaffSummary($combined_data);

?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Staff Attendance & Dispensing Report</h2>
    
    <!-- Filter Form -->
    <form method="POST" action="" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <!-- Period Type -->
            <div>
                <label for="period_type" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                <select id="period_type" name="period_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="daily" <?= $period_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $period_type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $period_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            
            <!-- Date Range -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <!-- Staff Filter -->
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                <select id="staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach ($staff_list as $staff): ?>
                    <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($staff['staff_name']) ?> (<?= htmlspecialchars($staff['staff_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
    </form>
    
    <!-- Staff Summary -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Staff Performance Summary</h3>
    
    <?php if (empty($staff_summary)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800 mb-6">
        <p class="font-medium">No attendance or dispensing data found for the selected criteria.</p>
        <p class="text-sm mt-1">Try adjusting your filters or date range.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto mb-6">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Staff</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Position</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Working Days</th>
                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Attendance</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Hours Worked</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Overtime</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Dispensed (L)</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Avg. Per Day (L)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($staff_summary as $staff): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($staff['staff_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($staff['staff_code']) ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-500"><?= htmlspecialchars($staff['position']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= number_format($staff['present_days']) ?> / <?= number_format($staff['total_days']) ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?php 
                                    if (isset($staff['attendance_percentage']) && $staff['attendance_percentage'] >= 90) echo 'bg-green-600';
                                    elseif (isset($staff['attendance_percentage']) && $staff['attendance_percentage'] >= 75) echo 'bg-yellow-500';
                                    else echo 'bg-red-500';
                                ?> h-2 rounded-full" style="width: <?= isset($staff['attendance_percentage']) ? min(100, $staff['attendance_percentage']) : 0 ?>%"></div>
                            </div>
                            <div class="text-xs text-center mt-1"><?= isset($staff['attendance_percentage']) ? number_format($staff['attendance_percentage'], 1) : '0.0' ?>%</div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($staff['total_hours'], 1) ?></td>
                        <td class="py-3 px-4 text-sm text-indigo-600 font-medium text-right"><?= number_format($staff['overtime_hours'], 1) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right font-medium"><?= number_format($staff['volume_dispensed'], 1) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= isset($staff['avg_liters_per_day']) ? number_format($staff['avg_liters_per_day'], 1) : '0.0' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Detailed Period Data -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Detailed <?= ucfirst($period_type) ?> Performance</h3>
    
    <?php if (empty($combined_data)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800">
        <p class="font-medium">No detailed data found for the selected criteria.</p>
        <p class="text-sm mt-1">Try adjusting your filters or date range.</p>
    </div>
    <?php else: ?>
    
    <!-- Tabs for each staff member if multiple staff are shown -->
    <?php if (!$staff_id && count($staff_summary) > 1): ?>
        <div class="mb-4 border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px" role="tablist">
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-2 px-4 border-b-2 border-blue-600 font-medium text-blue-600 staff-tab active" 
                            data-staff-id="all" aria-selected="true" role="tab">
                        All Staff
                    </button>
                </li>
                <?php 
                // Get unique staff members
                $unique_staff = [];
                foreach ($combined_data as $data) {
                    $staff_id = $data['staff_id'];
                    if (!isset($unique_staff[$staff_id])) {
                        $unique_staff[$staff_id] = $data;
                    }
                }
                
                foreach ($unique_staff as $staff): 
                ?>
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-2 px-4 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 staff-tab" 
                            data-staff-id="<?= $staff['staff_id'] ?>" aria-selected="false" role="tab">
                        <?= htmlspecialchars($staff['staff_name']) ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="overflow-x-auto tab-content" id="all-staff-content">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php if (!$staff_id): ?>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Staff</th>
                    <?php endif; ?>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Period</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Days Worked</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Hours</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Overtime</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Dispensed (L)</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Avg. Per Day (L)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($combined_data as $data): ?>
                    <tr class="hover:bg-gray-50 staff-row" data-staff-id="<?= $data['staff_id'] ?>">
                        <?php if (!$staff_id): ?>
                        <td class="py-3 px-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($data['staff_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($data['staff_code']) ?></div>
                        </td>
                        <?php endif; ?>
                        <td class="py-3 px-4 text-sm text-gray-800"><?= formatPeriodLabel($data['period_date'], $period_type) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= isset($data['present_days']) ? number_format($data['present_days']) : '0' ?> / <?= isset($data['total_days']) ? number_format($data['total_days']) : '0' ?>
                            <?php if (isset($data['absent_days']) && $data['absent_days'] > 0): ?>
                            <span class="text-xs text-red-600 ml-1">(<?= number_format($data['absent_days']) ?> absent)</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= isset($data['total_hours']) ? number_format($data['total_hours'], 1) : '0.0' ?></td>
                        <td class="py-3 px-4 text-sm text-indigo-600 font-medium text-right"><?= isset($data['overtime_hours']) ? number_format($data['overtime_hours'], 1) : '0.0' ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right font-medium"><?= isset($data['volume_dispensed']) ? number_format($data['volume_dispensed'], 1) : '0.0' ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= isset($data['avg_liters_per_day']) ? number_format($data['avg_liters_per_day'], 1) : '0.0' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Individual staff tab contents -->
    <?php 
    if (!$staff_id && count($staff_summary) > 1) {
        foreach ($unique_staff as $staff): 
    ?>
        <div class="overflow-x-auto tab-content hidden" id="staff-<?= $staff['staff_id'] ?>-content">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Period</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Days Worked</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Hours</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Overtime</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Dispensed (L)</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Avg. Per Day (L)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php 
                    // Filter data for this staff member
                    $staff_data = array_filter($combined_data, function($item) use ($staff) {
                        return $item['staff_id'] == $staff['staff_id'];
                    });
                    
                    foreach ($staff_data as $data): 
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 text-sm text-gray-800"><?= formatPeriodLabel($data['period_date'], $period_type) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right">
                                <?= isset($data['present_days']) ? number_format($data['present_days']) : '0' ?> / <?= isset($data['total_days']) ? number_format($data['total_days']) : '0' ?>
                                <?php if (isset($data['absent_days']) && $data['absent_days'] > 0): ?>
                                <span class="text-xs text-red-600 ml-1">(<?= number_format($data['absent_days']) ?> absent)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= isset($data['total_hours']) ? number_format($data['total_hours'], 1) : '0.0' ?></td>
                            <td class="py-3 px-4 text-sm text-indigo-600 font-medium text-right"><?= isset($data['overtime_hours']) ? number_format($data['overtime_hours'], 1) : '0.0' ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right font-medium"><?= isset($data['volume_dispensed']) ? number_format($data['volume_dispensed'], 1) : '0.0' ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= isset($data['avg_liters_per_day']) ? number_format($data['avg_liters_per_day'], 1) : '0.0' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php 
        endforeach;
    }
    ?>
    
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="flex justify-end mt-4 space-x-2">
        <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-print mr-2"></i> Print
        </button>
        <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
            <i class="fas fa-file-pdf mr-2"></i> PDF
        </button>
        <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-file-excel mr-2"></i> Excel
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching functionality for staff tabs
        const staffTabs = document.querySelectorAll('.staff-tab');
        if (staffTabs.length > 0) {
            staffTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    staffTabs.forEach(t => {
                        t.classList.remove('border-blue-600', 'text-blue-600');
                        t.classList.add('border-transparent', 'text-gray-500');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.remove('border-transparent', 'text-gray-500');
                    this.classList.add('border-blue-600', 'text-blue-600');
                    
                    // Hide all tab contents
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.add('hidden');
                    });
                    
                    // Show selected tab content
                    const staffId = this.dataset.staffId;
                    if (staffId === 'all') {
                        document.getElementById('all-staff-content').classList.remove('hidden');
                    } else {
                        document.getElementById(`staff-${staffId}-content`).classList.remove('hidden');
                    }
                });
            });
        }
        
        // For all staff view, filter rows based on selected tab
        if (staffTabs.length > 0) {
            staffTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const staffId = this.dataset.staffId;
                    
                    if (staffId === 'all') {
                        // Show all rows
                        document.querySelectorAll('.staff-row').forEach(row => {
                            row.classList.remove('hidden');
                        });
                    } else {
                        // Hide all rows, then show only matching rows
                        document.querySelectorAll('.staff-row').forEach(row => {
                            if (row.dataset.staffId === staffId) {
                                row.classList.remove('hidden');
                            } else {
                                row.classList.add('hidden');
                            }
                        });
                    }
                });
            });
        }
    });
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF (placeholder)
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library
    }
    
    // Export to Excel (placeholder)
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require a spreadsheet library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';
ob_end_flush();
?>