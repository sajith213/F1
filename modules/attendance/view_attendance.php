<?php
/**
 * View Attendance Records
 * 
 * This file displays attendance records with filtering options
 */

// Set page title
$page_title = "Attendance Records";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Attendance</a> / <span>View Records</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Query database connection
require_once('../../includes/db.php');

// Get all staff for filter dropdown
$staff_query = "SELECT staff_id, first_name, last_name, staff_code FROM staff WHERE status = 'active' ORDER BY first_name, last_name";
$staff_result = $conn->query($staff_query);
$staff_list = [];
while ($row = $staff_result->fetch_assoc()) {
    $staff_list[] = $row;
}

// Default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

// Get attendance records based on filters
$attendance_records = getAttendanceRecords($start_date, $end_date, $staff_id, $status);

// Calculate summary
$total_records = count($attendance_records);
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$half_day_count = 0;
$leave_count = 0;
$total_hours = 0;

foreach ($attendance_records as $record) {
    switch ($record['status']) {
        case 'present':
            $present_count++;
            break;
        case 'absent':
            $absent_count++;
            break;
        case 'late':
            $late_count++;
            break;
        case 'half_day':
            $half_day_count++;
            break;
        case 'leave':
            $leave_count++;
            break;
    }
    $total_hours += $record['hours_worked'] ?? 0;
}

// Success message handling
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Attendance record updated successfully.";
}
?>

<!-- Search and Filter Form -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Filter Attendance Records</h2>
    </div>
    
    <div class="p-4">
        <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?= $success_message ?></p>
        </div>
        <?php endif; ?>
        
        <form action="view_attendance.php" method="get">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Date Range -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <!-- Staff Filter -->
                <div>
                    <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                    <select id="staff_id" name="staff_id" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Staff Members</option>
                        <?php foreach ($staff_list as $staff): ?>
                            <?php $selected = ($staff_id == $staff['staff_id']) ? 'selected' : ''; ?>
                            <option value="<?= $staff['staff_id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (' . $staff['staff_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="present" <?= ($status == 'present') ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= ($status == 'absent') ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= ($status == 'late') ? 'selected' : '' ?>>Late</option>
                        <option value="half_day" <?= ($status == 'half_day') ? 'selected' : '' ?>>Half Day</option>
                        <option value="leave" <?= ($status == 'leave') ? 'selected' : '' ?>>Leave</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                
                <div>
                    <!-- Quick Filter Buttons -->
                    <a href="view_attendance.php?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition mr-2">
                        Today
                    </a>
                    <a href="view_attendance.php?start_date=<?= date('Y-m-d', strtotime('this week')) ?>&end_date=<?= date('Y-m-d') ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition mr-2">
                        This Week
                    </a>
                    <a href="view_attendance.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition">
                        This Month
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <!-- Total Records -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Total Records</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $total_records ?></p>
    </div>
    
    <!-- Present -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Present</h3>
        <p class="text-2xl font-bold text-green-600"><?= $present_count ?></p>
        <div class="text-xs text-gray-500"><?= $total_records > 0 ? round(($present_count / $total_records) * 100, 1) : 0 ?>%</div>
    </div>
    
    <!-- Absent -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Absent</h3>
        <p class="text-2xl font-bold text-red-600"><?= $absent_count ?></p>
        <div class="text-xs text-gray-500"><?= $total_records > 0 ? round(($absent_count / $total_records) * 100, 1) : 0 ?>%</div>
    </div>
    
    <!-- Late -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Late</h3>
        <p class="text-2xl font-bold text-yellow-600"><?= $late_count ?></p>
        <div class="text-xs text-gray-500"><?= $total_records > 0 ? round(($late_count / $total_records) * 100, 1) : 0 ?>%</div>
    </div>
    
    <!-- Half Day -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Half Day</h3>
        <p class="text-2xl font-bold text-orange-600"><?= $half_day_count ?></p>
        <div class="text-xs text-gray-500"><?= $total_records > 0 ? round(($half_day_count / $total_records) * 100, 1) : 0 ?>%</div>
    </div>
    
    <!-- Total Hours -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Total Hours</h3>
        <p class="text-2xl font-bold text-blue-600"><?= number_format($total_hours, 1) ?></p>
        <div class="text-xs text-gray-500">Average: <?= $present_count > 0 ? number_format($total_hours / $present_count, 1) : 0 ?> hrs/day</div>
    </div>
</div>

<!-- Attendance Records Table -->
<div class="bg-white rounded-lg shadow-md">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Attendance Records</h2>
        <div>
            <a href="record_attendance.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-plus mr-2"></i> Record Attendance
            </a>
            <button type="button" onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition ml-2">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>
    </div>
    
    <div class="p-4 overflow-x-auto">
        <?php if (count($attendance_records) > 0): ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($attendance_records as $record): ?>
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            <?= date('d M, Y', strtotime($record['attendance_date'])) ?>
                        </div>
                        <div class="text-xs text-gray-500"><?= date('l', strtotime($record['attendance_date'])) ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex items-center">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                </div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($record['staff_code']) ?></div>
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
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($record['recorded_by_name'] ?? 'System') ?>
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
            <p class="text-gray-500">No attendance records found for the selected criteria.</p>
            <a href="record_attendance.php" class="mt-2 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                Record Attendance
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once('../../includes/footer.php');
?>