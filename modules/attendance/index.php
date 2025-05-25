<?php
/**
 * Attendance Module - Main Dashboard
 * 
 * This file displays the attendance dashboard with key metrics and recent records
 */

// Set page title
$page_title = "Attendance Dashboard";
$breadcrumbs = '<a href="../../index.php">Home</a> / <span>Attendance</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Default to current month for summary
$current_month = date('Y-m');
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Get attendance data for current month
$today = date('Y-m-d');
$attendance_summary = getAttendanceSummary($start_date, $end_date);
$recent_records = getAttendanceRecords($today, $today);

// Calculate summary statistics
$total_staff = count($attendance_summary);
$present_today = 0;
$absent_today = 0;
$late_today = 0;

foreach ($recent_records as $record) {
    if ($record['status'] == 'present') $present_today++;
    if ($record['status'] == 'absent') $absent_today++;
    if ($record['status'] == 'late') $late_today++;
}
?>

<!-- Dashboard Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Today's Attendance Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Today's Attendance</h3>
                <p class="text-2xl font-bold text-blue-600"><?= $present_today ?> / <?= $total_staff ?></p>
            </div>
            <div class="rounded-full bg-blue-100 p-2">
                <i class="fas fa-user-check text-blue-500"></i>
            </div>
        </div>
        <div class="flex justify-between mt-2 text-sm">
            <span class="text-green-500">Present: <?= $present_today ?></span>
            <span class="text-red-500">Absent: <?= $absent_today ?></span>
            <span class="text-yellow-500">Late: <?= $late_today ?></span>
        </div>
    </div>

    <!-- Average Hours Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Avg. Working Hours</h3>
                <?php
                $total_hours = 0;
                $days_with_hours = 0;
                foreach ($attendance_summary as $summary) {
                    if ($summary['total_hours'] > 0) {
                        $total_hours += $summary['total_hours'];
                        $days_with_hours++;
                    }
                }
                $avg_hours = $days_with_hours > 0 ? round($total_hours / $days_with_hours, 1) : 0;
                ?>
                <p class="text-2xl font-bold text-indigo-600"><?= $avg_hours ?> hrs</p>
            </div>
            <div class="rounded-full bg-indigo-100 p-2">
                <i class="fas fa-clock text-indigo-500"></i>
            </div>
        </div>
        <div class="mt-2 text-sm text-gray-500">
            For the month of <?= date('F Y') ?>
        </div>
    </div>

    <!-- Overtime Hours Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Total Overtime</h3>
                <?php
                $total_overtime = 0;
                foreach ($attendance_summary as $summary) {
                    $total_overtime += $summary['total_overtime'] ?? 0;
                }
                ?>
                <p class="text-2xl font-bold text-purple-600"><?= round($total_overtime, 1) ?> hrs</p>
            </div>
            <div class="rounded-full bg-purple-100 p-2">
                <i class="fas fa-business-time text-purple-500"></i>
            </div>
        </div>
        <div class="mt-2 text-sm text-gray-500">
            For the month of <?= date('F Y') ?>
        </div>
    </div>

    <!-- Absence Rate Card -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Absence Rate</h3>
                <?php
                $total_days = 0;
                $absent_days = 0;
                foreach ($attendance_summary as $summary) {
                    $total_days += $summary['total_days'] ?? 0;
                    $absent_days += $summary['absent_days'] ?? 0;
                }
                $absence_rate = $total_days > 0 ? round(($absent_days / $total_days) * 100, 1) : 0;
                ?>
                <p class="text-2xl font-bold text-red-600"><?= $absence_rate ?>%</p>
            </div>
            <div class="rounded-full bg-red-100 p-2">
                <i class="fas fa-user-slash text-red-500"></i>
            </div>
        </div>
        <div class="mt-2 text-sm text-gray-500">
            For the month of <?= date('F Y') ?>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="flex flex-wrap justify-between mb-6 gap-2">
    <div class="flex flex-wrap gap-2">
        <a href="record_attendance.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-user-clock mr-2"></i> Record Attendance
        </a>
        <a href="view_attendance.php" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-calendar-check mr-2"></i> View Records
        </a>
        <a href="overtime_report.php" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition">
            <i class="fas fa-chart-line mr-2"></i> Overtime Report
        </a>
    </div>
    
    <form action="index.php" method="get" class="flex gap-2">
        <select name="month" class="form-select rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            <?php
            // Generate month options
            for ($i = 0; $i < 12; $i++) {
                $month = date('Y-m', strtotime("-$i months"));
                $month_name = date('F Y', strtotime("-$i months"));
                $selected = ($month == $current_month) ? 'selected' : '';
                echo "<option value=\"$month\" $selected>$month_name</option>";
            }
            ?>
        </select>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
            Update
        </button>
    </form>
</div>

<!-- Today's Attendance Section -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Today's Attendance (<?= date('d M, Y') ?>)</h2>
    </div>
    <div class="p-4 overflow-x-auto">
        <?php if (count($recent_records) > 0): ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($recent_records as $record): ?>
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($record['position']) ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <?php
                        $status_color = '';
                        switch ($record['status']) {
                            case 'present':
                                $status_color = 'bg-green-100 text-green-800';
                                break;
                            case 'absent':
                                $status_color = 'bg-red-100 text-red-800';
                                break;
                            case 'late':
                                $status_color = 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'half_day':
                                $status_color = 'bg-orange-100 text-orange-800';
                                break;
                            case 'leave':
                                $status_color = 'bg-blue-100 text-blue-800';
                                break;
                        }
                        ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                            <?= ucfirst($record['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-' ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= $record['hours_worked'] ? number_format($record['hours_worked'], 1) : '-' ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                        <a href="record_attendance.php?id=<?= $record['attendance_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($record['status'] == 'present' && $record['hours_worked'] > 8): ?>
                        <a href="overtime_report.php?attendance_id=<?= $record['attendance_id'] ?>" class="text-purple-600 hover:text-purple-900" title="Record Overtime">
                            <i class="fas fa-business-time"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-center py-4">
            <p class="text-gray-500">No attendance records for today.</p>
            <a href="record_attendance.php" class="mt-2 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                Record Attendance
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly Attendance Summary -->
<div class="bg-white rounded-lg shadow-md">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Monthly Attendance Summary (<?= date('F Y') ?>)</h2>
        <a href="view_attendance.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="p-4 overflow-x-auto">
        <?php if (count($attendance_summary) > 0): ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Absent</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Late</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Half Day</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Hours</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overtime</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($attendance_summary as $summary): ?>
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($summary['first_name'] . ' ' . $summary['last_name']) ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($summary['position']) ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-green-600 font-medium">
                        <?= $summary['present_days'] ?? 0 ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-red-600 font-medium">
                        <?= $summary['absent_days'] ?? 0 ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-yellow-600 font-medium">
                        <?= $summary['late_days'] ?? 0 ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-orange-600 font-medium">
                        <?= $summary['half_days'] ?? 0 ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-blue-600 font-medium">
                        <?= $summary['leave_days'] ?? 0 ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 font-medium">
                        <?= number_format($summary['total_hours'] ?? 0, 1) ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-purple-600 font-medium">
                        <?= number_format($summary['total_overtime'] ?? 0, 1) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-center py-4">
            <p class="text-gray-500">No attendance summary available for this month.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once('../../includes/footer.php');
?>