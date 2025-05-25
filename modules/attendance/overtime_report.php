<?php
/**
 * Overtime Report
 *
 * This file displays and manages overtime records
 */

// Set page title
$page_title = "Overtime Report";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Attendance</a> / <span>Overtime Report</span>';

// Include header
include_once('../../includes/header.php');

// Include module functions
require_once('functions.php'); // Make sure recordOvertime in this file handles the amount calculation correctly with the new rate

// Process form submission for approval/rejection
$success_message = '';
$error_message = '';

// MODIFIED: Define the fixed overtime rate
define('FIXED_OVERTIME_RATE', 1.64);
define('LUNCH_BREAK_DEDUCTION_HOURS', 1.0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve' || $_POST['action'] === 'reject') {
        $overtime_id = $_POST['overtime_id'] ?? null;
        $status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
        $notes = $_POST['notes'] ?? null;

        if ($overtime_id) {
            $result = updateOvertimeStatus($overtime_id, $status, $_SESSION['user_id'], $notes);

            if ($result === true) {
                $success_message = "Overtime record " . ucfirst($status) . " successfully.";
            } else {
                $error_message = $result;
            }
        }
    } elseif ($_POST['action'] === 'record') {
        $attendance_id = $_POST['attendance_id'] ?? null;
        // MODIFIED: Get the raw overtime hours input by the user
        $raw_overtime_hours = isset($_POST['overtime_hours']) ? floatval($_POST['overtime_hours']) : 0;
        // MODIFIED: Overtime rate is now fixed
        $overtime_rate = FIXED_OVERTIME_RATE;
        $notes = $_POST['notes'] ?? null;

        if ($attendance_id && $raw_overtime_hours > 0) {
            // MODIFIED: Calculate effective overtime hours after deducting lunch break
            $effective_overtime_hours = $raw_overtime_hours;
            if ($raw_overtime_hours > LUNCH_BREAK_DEDUCTION_HOURS) {
                $effective_overtime_hours = $raw_overtime_hours - LUNCH_BREAK_DEDUCTION_HOURS;
            } else {
                // If raw overtime is 1 hour or less, after deduction it becomes 0 or negative.
                // So, effectively 0 payable overtime hours.
                $effective_overtime_hours = 0;
            }

            if ($effective_overtime_hours > 0) {
                // IMPORTANT: Ensure your recordOvertime function in functions.php:
                // 1. Accepts $effective_overtime_hours and $overtime_rate.
                // 2. Calculates 'overtime_amount' based on these and the employee's base hourly pay.
                //    e.g., overtime_amount = effective_overtime_hours * overtime_rate * employee_base_hourly_rate
                $result = recordOvertime($attendance_id, $effective_overtime_hours, $overtime_rate, $notes);

                if ($result === true) {
                    $success_message = "Overtime record added successfully. Effective Hours: " . number_format($effective_overtime_hours, 2) . " at rate " . $overtime_rate . "x.";
                } else {
                    $error_message = $result; // Assumes recordOvertime returns an error message string
                }
            } else {
                $error_message = "After deducting " . LUNCH_BREAK_DEDUCTION_HOURS . " hour(s) for lunch, the effective overtime is 0 or less. No overtime recorded.";
            }
        } else {
            $error_message = "Please provide valid attendance ID and total overtime hours (must be > 0).";
        }
    }
}

// Default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

// Get all staff for filter dropdown
$staff_query = "SELECT staff_id, first_name, last_name, staff_code FROM staff WHERE status = 'active' ORDER BY first_name, last_name";
$staff_result = $conn->query($staff_query);
$staff_list = [];
while ($row = $staff_result->fetch_assoc()) {
    $staff_list[] = $row;
}

// Check if viewing a specific attendance record for overtime recording
$specific_attendance = null;
if (isset($_GET['attendance_id']) && is_numeric($_GET['attendance_id'])) {
    $attendance_id = $_GET['attendance_id'];

    $query = "SELECT ar.*, s.first_name, s.last_name, s.staff_code, s.position,
                    (SELECT ot.overtime_id FROM overtime_records ot WHERE ot.attendance_id = ar.attendance_id LIMIT 1) as has_overtime -- MODIFIED: Added LIMIT 1
              FROM attendance_records ar
              JOIN staff s ON ar.staff_id = s.staff_id
              WHERE ar.attendance_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $specific_attendance = $row;
    }

    $stmt->close();
}

// Get overtime records based on filters
$overtime_records = getOvertimeRecords($start_date, $end_date, $staff_id, $status);

// Calculate summary
$total_records = count($overtime_records);
$approved_count = 0;
$pending_count = 0;
$rejected_count = 0;
$total_overtime_hours = 0;
$total_overtime_amount = 0;

foreach ($overtime_records as $record) {
    switch ($record['status']) {
        case 'approved':
            $approved_count++;
            break;
        case 'pending':
            $pending_count++;
            break;
        case 'rejected':
            $rejected_count++;
            break;
    }
    $total_overtime_hours += $record['overtime_hours'] ?? 0;
    $total_overtime_amount += $record['overtime_amount'] ?? 0;
}
?>

<?php if ($specific_attendance): ?>
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">
            Record Overtime for <?= htmlspecialchars($specific_attendance['first_name'] . ' ' . $specific_attendance['last_name']) ?>
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

        <?php if ($specific_attendance['has_overtime']): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                <p>Overtime has already been recorded for this attendance record. You might need to edit the existing record or contact an administrator.</p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <p><strong>Staff:</strong> <?= htmlspecialchars($specific_attendance['first_name'] . ' ' . $specific_attendance['last_name']) ?> (<?= $specific_attendance['staff_code'] ?>)</p>
                <p><strong>Position:</strong> <?= htmlspecialchars($specific_attendance['position']) ?></p>
                <p><strong>Date:</strong> <?= date('d M, Y', strtotime($specific_attendance['attendance_date'])) ?></p>
            </div>
            <div>
                <p><strong>Time In:</strong> <?= $specific_attendance['time_in'] ? date('h:i A', strtotime($specific_attendance['time_in'])) : '-' ?></p>
                <p><strong>Time Out:</strong> <?= $specific_attendance['time_out'] ? date('h:i A', strtotime($specific_attendance['time_out'])) : '-' ?></p>
                <p><strong>Hours Worked:</strong> <?= number_format($specific_attendance['hours_worked'], 1) ?> hours</p>
            </div>
        </div>

        <hr class="my-4 border-gray-200">

        <form action="overtime_report.php?attendance_id=<?= $specific_attendance['attendance_id'] // MODIFIED: Keep attendance_id in URL for context if form fails ?>" method="post">
            <input type="hidden" name="action" value="record">
            <input type="hidden" name="attendance_id" value="<?= $specific_attendance['attendance_id'] ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="overtime_hours" class="block text-sm font-medium text-gray-700 mb-1">Total Overtime Hours Worked *</label>
                    <input type="number" id="overtime_hours" name="overtime_hours" step="0.1" min="0.1" max="12"
                           value="<?= htmlspecialchars(max(($specific_attendance['hours_worked'] - 8), 0)) ?>"
                           class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                    <div class="mt-1 text-xs text-gray-500">
                        Standard work day: 8.0 hours.
                        Enter total hours worked beyond standard.
                        <?= LUNCH_BREAK_DEDUCTION_HOURS ?> hour(s) will be deducted for lunch.
                        E.g., input 2.5 hrs -> payable OT: <?= max(0, 2.5 - LUNCH_BREAK_DEDUCTION_HOURS) ?> hrs.
                        Input 1 hr -> payable OT: <?= max(0, 1 - LUNCH_BREAK_DEDUCTION_HOURS) ?> hrs.
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Overtime Rate</label>
                    <input type="text" value="<?= FIXED_OVERTIME_RATE ?>x"
                           class="form-input w-full rounded-md border-gray-300 shadow-sm bg-gray-100" readonly>
                    <div class="mt-1 text-xs text-gray-500">This rate is fixed.</div>
                </div>
            </div>

            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="form-textarea w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"></textarea>
            </div>

            <div class="flex justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition" <?= $specific_attendance['has_overtime'] ? 'disabled title="Overtime already recorded"' : '' ?>>
                    Record Overtime
                </button>
                <a href="view_attendance.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-lg font-semibold text-gray-800">Filter Overtime Records</h2>
    </div>

    <div class="p-4">
        <?php if (!$specific_attendance && $success_message): // MODIFIED: only show general messages if not in specific attendance view ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?= $success_message ?></p>
        </div>
        <?php endif; ?>

        <?php if (!$specific_attendance && $error_message): // MODIFIED: only show general messages if not in specific attendance view ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?= $error_message ?></p>
        </div>
        <?php endif; ?>

        <form action="overtime_report.php" method="get">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                    <select id="staff_id" name="staff_id" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Staff Members</option>
                        <?php foreach ($staff_list as $staff_member): ?>
                            <?php $selected = ($staff_id == $staff_member['staff_id']) ? 'selected' : ''; ?>
                            <option value="<?= $staff_member['staff_id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name'] . ' (' . $staff_member['staff_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label> <select id="status_filter" name="status" class="form-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= ($status == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($status == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= ($status == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-between items-center">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>

                <div>
                    <a href="overtime_report.php?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition mr-2 text-sm">
                        Today
                    </a>
                    <a href="overtime_report.php?start_date=<?= date('Y-m-d', strtotime('monday this week')) ?>&end_date=<?= date('Y-m-d', strtotime('sunday this week')) ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition mr-2 text-sm"> This Week
                    </a>
                    <a href="overtime_report.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition text-sm">
                        This Month
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Total Records</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $total_records ?></p>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Pending</h3>
        <p class="text-2xl font-bold text-yellow-600"><?= $pending_count ?></p>
        <div class="text-xs text-gray-500"><?= $total_records > 0 ? round(($pending_count / $total_records) * 100, 1) : 0 ?>%</div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Approved</h3>
        <p class="text-2xl font-bold text-green-600"><?= $approved_count ?></p>
        <div class="text-xs text-gray-500"><?= $total_records > 0 ? round(($approved_count / $total_records) * 100, 1) : 0 ?>%</div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500">Total Overtime Hours (Approved/Pending)</h3>
        <p class="text-2xl font-bold text-purple-600"><?= number_format($total_overtime_hours, 1) ?></p>
        <div class="text-xs text-gray-500">Value: <?= CURRENCY_SYMBOL ?? '$' ?><?= number_format($total_overtime_amount, 2) ?></div> </div>
</div>

<div class="bg-white rounded-lg shadow-md">
    <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Overtime Records</h2>
        <div>
            <a href="view_attendance.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                <i class="fas fa-calendar-check mr-2"></i> View Attendance
            </a>
            <button type="button" onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition ml-2">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>
    </div>

    <div class="p-4 overflow-x-auto">
        <?php if (count($overtime_records) > 0): ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Std. Hours</th> <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OT Hours</th> <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($overtime_records as $record): ?>
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
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($record['staff_code']) ?> | <?= htmlspecialchars($record['position']) ?></div> </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= number_format(min($record['hours_worked'] ?? 8, 8), 1) ?> hrs </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-purple-600 font-medium">
                        <?= number_format($record['overtime_hours'], 1) ?> hrs
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= number_format($record['overtime_rate'], 2) ?>x </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                        <?= CURRENCY_SYMBOL ?? '$' ?><?= number_format($record['overtime_amount'], 2) ?> </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <?php
                        $status_color = '';
                        switch ($record['status']) {
                            case 'approved':
                                $status_color = 'bg-green-100 text-green-800';
                                break;
                            case 'pending':
                                $status_color = 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'rejected':
                                $status_color = 'bg-red-100 text-red-800';
                                break;
                            default:
                                $status_color = 'bg-gray-100 text-gray-800';
                        }
                        ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                            <?= ucfirst(htmlspecialchars($record['status'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                        <?php if ($record['status'] === 'pending' && (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'))): ?>
                        <button type="button" class="text-green-600 hover:text-green-900 mr-3"
                                onclick="showApprovalModal(<?= $record['overtime_id'] ?>, 'approve')" title="Approve">
                            <i class="fas fa-check-circle"></i>
                        </button>
                        <button type="button" class="text-red-600 hover:text-red-900"
                                onclick="showApprovalModal(<?= $record['overtime_id'] ?>, 'reject')" title="Reject">
                            <i class="fas fa-times-circle"></i>
                        </button>
                        <?php elseif ($record['status'] !== 'pending'): ?>
                             <span class="text-xs text-gray-500 italic">
                               Processed
                            </span>
                        <?php else: ?>
                        <span class="text-gray-400" title="Action locked">
                            <i class="fas fa-lock"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-center py-4">
            <p class="text-gray-500">No overtime records found for the selected criteria.</p>
            <a href="view_attendance.php" class="mt-2 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                View Attendance Records
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-4">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-lg font-semibold text-gray-800">Approve Overtime</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700" onclick="hideApprovalModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="approvalForm" action="overtime_report.php" method="post">
            <input type="hidden" id="modal_overtime_id" name="overtime_id" value=""> <input type="hidden" id="modal_action" name="action" value=""> <div class="mb-4">
                <label for="modal_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                <textarea id="modal_notes" name="notes" rows="3" class="form-textarea w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition mr-2" onclick="hideApprovalModal()">
                    Cancel
                </button>
                <button type="submit" id="submitApprovalBtn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Approve
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showApprovalModal(overtimeId, action) {
    document.getElementById('modal_overtime_id').value = overtimeId; // MODIFIED: id changed
    document.getElementById('modal_action').value = action; // MODIFIED: id changed

    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitApprovalBtn');

    if (action === 'approve') {
        modalTitle.innerText = 'Approve Overtime';
        submitBtn.innerText = 'Approve';
        submitBtn.className = 'bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition';
    } else { // reject
        modalTitle.innerText = 'Reject Overtime';
        submitBtn.innerText = 'Reject';
        submitBtn.className = 'bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition';
    }

    document.getElementById('modal_notes').value = ''; // Clear previous notes
    document.getElementById('approvalModal').classList.remove('hidden');
    document.getElementById('approvalModal').classList.add('flex'); // Ensure it's flex for centering
}

function hideApprovalModal() {
    document.getElementById('approvalModal').classList.add('hidden');
    document.getElementById('approvalModal').classList.remove('flex');
}
</script>

<?php
// Include footer
include_once('../../includes/footer.php');
?>