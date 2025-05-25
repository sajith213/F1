<?php
/**
 * Record Attendance
 * 
 * This file handles recording daily attendance for staff members
 */
ob_start();
// Set page title
$page_title = "Record Attendance";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Attendance</a> / <span>Record Attendance</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php');

// Default to today
$attendance_date = date('Y-m-d');
$editing = false;
$attendance_record = null;

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $staff_id = $_POST['staff_id'] ?? null;
    $date = $_POST['attendance_date'] ?? null;
    $status = $_POST['status'] ?? null;
    $time_in = !empty($_POST['time_in']) ? date('Y-m-d H:i:s', strtotime("$date {$_POST['time_in']}")) : null;
    $time_out = !empty($_POST['time_out']) ? date('Y-m-d H:i:s', strtotime("$date {$_POST['time_out']}")) : null;
    $remarks = $_POST['remarks'] ?? null;
    
    // Validation
    if (!$staff_id || !$date || !$status) {
        $error_message = "Please provide all required fields.";
    } else {
        // Record attendance
        $record_result = recordAttendance(
            $staff_id, 
            $date, 
            $status, 
            $time_in, 
            $time_out, 
            $remarks, 
            $_SESSION['user_id']
        );
        
        if ($record_result === true) {
            $success_message = "Attendance recorded successfully.";
            
            // If time_in and time_out provided, check for overtime
            if ($time_in && $time_out) {
                $hours = calculateHours($time_in, $time_out);
                if ($hours > 8) {
                    // Get attendance record ID
                    $record = getAttendanceRecord($staff_id, $date);
                    if ($record) {
                        $overtime_hours = $hours - 8;
                        recordOvertime($record['attendance_id'], $overtime_hours);
                    }
                }
            }
            
            // Redirect to prevent form resubmission
            header("Location: index.php?success=1");
            exit;
        } else {
            $error_message = $record_result;
        }
    }
}

// Check if we're editing an existing record
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $attendance_id = $_GET['id'];
    $query = "SELECT * FROM attendance_records WHERE attendance_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $attendance_record = $row;
        $attendance_date = $row['attendance_date'];
        $editing = true;
    }
    
    $stmt->close();
}

// If a date is specified in the URL, use it
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $attendance_date = $_GET['date'];
}

// Get all active staff
$staff = getStaffForAttendance();
?>

<div class="bg-white rounded-lg shadow-md">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">
            <?= $editing ? 'Edit Attendance' : 'Record Attendance' ?>
        </h2>
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
        
        <form action="record_attendance.php" method="post">
            <?php if ($attendance_record): ?>
            <input type="hidden" name="attendance_id" value="<?= $attendance_record['attendance_id'] ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Staff Selection -->
                <div>
                    <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member *</label>
                    <select id="staff_id" name="staff_id" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required <?= $editing ? 'disabled' : '' ?>>
                        <option value="">Select Staff Member</option>
                        <?php foreach ($staff as $staff_member): ?>
                            <?php $selected = ($attendance_record && $attendance_record['staff_id'] == $staff_member['staff_id']) ? 'selected' : ''; ?>
                            <option value="<?= $staff_member['staff_id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name'] . ' (' . $staff_member['staff_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editing): ?>
                    <input type="hidden" name="staff_id" value="<?= $attendance_record['staff_id'] ?>">
                    <?php endif; ?>
                </div>
                
                <!-- Date Selection -->
                <div>
                    <label for="attendance_date" class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input type="date" id="attendance_date" name="attendance_date" value="<?= $attendance_date ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required <?= $editing ? 'readonly' : '' ?>>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select id="status" name="status" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <option value="">Select Status</option>
                        <option value="present" <?= ($attendance_record && $attendance_record['status'] == 'present') ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= ($attendance_record && $attendance_record['status'] == 'absent') ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= ($attendance_record && $attendance_record['status'] == 'late') ? 'selected' : '' ?>>Late</option>
                        <option value="half_day" <?= ($attendance_record && $attendance_record['status'] == 'half_day') ? 'selected' : '' ?>>Half Day</option>
                        <option value="leave" <?= ($attendance_record && $attendance_record['status'] == 'leave') ? 'selected' : '' ?>>Leave</option>
                    </select>
                </div>
                
                <!-- Time In -->
                <div>
                    <label for="time_in" class="block text-sm font-medium text-gray-700 mb-1">Time In</label>
                    <input type="time" id="time_in" name="time_in" value="<?= $attendance_record && $attendance_record['time_in'] ? date('H:i', strtotime($attendance_record['time_in'])) : '' ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
                
                <!-- Time Out -->
                <div>
                    <label for="time_out" class="block text-sm font-medium text-gray-700 mb-1">Time Out</label>
                    <input type="time" id="time_out" name="time_out" value="<?= $attendance_record && $attendance_record['time_out'] ? date('H:i', strtotime($attendance_record['time_out'])) : '' ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>
            </div>
            
            <!-- Remarks -->
            <div class="mb-4">
                <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" class="form-textarea w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"><?= $attendance_record ? htmlspecialchars($attendance_record['remarks']) : '' ?></textarea>
            </div>
            
            <div class="flex justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    <?= $editing ? 'Update Attendance' : 'Record Attendance' ?>
                </button>
                <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Batch Attendance Recording -->
<?php if (!$editing): ?>
<div class="bg-white rounded-lg shadow-md mt-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Batch Attendance Recording</h2>
    </div>
    
    <div class="p-4">
        <form action="batch_record.php" method="post" id="batchForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="batch_date" class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input type="date" id="batch_date" name="batch_date" value="<?= $attendance_date ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                </div>
                
                <div class="self-end">
                    <button type="button" id="checkAllBtn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition mr-2">
                        Check All Present
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                        Record Batch
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                            <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($staff as $index => $staff_member): ?>
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex items-center">
                                    <input type="checkbox" name="staff_ids[]" value="<?= $staff_member['staff_id'] ?>" class="staff-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($staff_member['staff_code']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($staff_member['position']) ?></div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <select name="batch_status[<?= $staff_member['staff_id'] ?>]" class="form-select rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 text-sm">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="half_day">Half Day</option>
                                    <option value="leave">Leave</option>
                                </select>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <input type="time" name="batch_time_in[<?= $staff_member['staff_id'] ?>]" class="form-input rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 text-sm">
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <input type="time" name="batch_time_out[<?= $staff_member['staff_id'] ?>]" class="form-input rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 text-sm">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status field change handler
    const statusField = document.getElementById('status');
    const timeInField = document.getElementById('time_in');
    const timeOutField = document.getElementById('time_out');
    
    statusField.addEventListener('change', function() {
        const status = this.value;
        if (status === 'absent' || status === 'leave') {
            timeInField.value = '';
            timeOutField.value = '';
            timeInField.disabled = true;
            timeOutField.disabled = true;
        } else {
            timeInField.disabled = false;
            timeOutField.disabled = false;
            
            if (status === 'present' && !timeInField.value) {
                timeInField.value = '09:00';
                timeOutField.value = '17:00';
            }
        }
    });
    
    // Check all button
    const checkAllBtn = document.getElementById('checkAllBtn');
    const staffCheckboxes = document.querySelectorAll('.staff-checkbox');
    
    checkAllBtn.addEventListener('click', function() {
        staffCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
    });
    
    // Trigger initial status check
    if (statusField.value) {
        statusField.dispatchEvent(new Event('change'));
    }
});
</script>
<?php endif; ?>

<?php
// Include footer
include_once('../../includes/footer.php');
ob_end_flush();
?>