<?php
/**
 * Assign Staff to Pumps
 * 
 * Interface for assigning staff members to pumps for shifts
 */
ob_start();
// Set page title and load header
$page_title = "Assign Staff to Pumps";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Staff Management</a> / Assign Staff";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check if user has permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Get active pumps
$active_pumps = getActivePumps($conn);

// Get active staff
$active_staff = getActiveStaff($conn);

// Get the date for assignments
$assignment_date = $_GET['date'] ?? date('Y-m-d');

// Filter out staff who are on leave for the selected date
$staff_on_leave = [];
$query = "SELECT staff_id FROM attendance_records 
          WHERE attendance_date = ? AND (status = 'leave' OR status = 'absent')";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $assignment_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $staff_on_leave[] = $row['staff_id'];
}
$stmt->close();

// Filter active staff to only include those who are not on leave
$present_staff = [];
foreach ($active_staff as $staff) {
    if (!in_array($staff['staff_id'], $staff_on_leave)) {
        $present_staff[] = $staff;
    }
}

// Pre-select a staff member if provided in URL
$selected_staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : null;
$selected_staff = null;

if ($selected_staff_id) {
    foreach ($present_staff as $staff) {
        if ($staff['staff_id'] == $selected_staff_id) {
            $selected_staff = $staff;
            break;
        }
    }
}

// Define shifts
$shifts = ['morning', 'afternoon', 'evening', 'night'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
    $error_message = null;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First, get all existing assignments for the date
        $existing_assignments = getStaffAssignments($conn, $_POST['assignment_date']);
        $existing_map = [];
        foreach ($existing_assignments as $assignment) {
            $existing_map[$assignment['pump_id']][$assignment['shift']] = $assignment['assignment_id'];
        }
        
        // Process assignments - handle updates, new assignments, and removals
        foreach ($_POST['assignments'] as $pump_id => $shifts) {
            foreach ($shifts as $shift => $staff_id) {
                // Check if there's an existing assignment for this pump/shift
                $assignment_id = $existing_map[$pump_id][$shift] ?? null;
                
                if (empty($staff_id) && $assignment_id) {
                    // If no staff selected but there is an existing assignment, remove it
                    $result = removeStaffAssignment($conn, $assignment_id);
                    if (!$result) {
                        throw new Exception("Failed to remove staff assignment for pump #$pump_id on $shift shift.");
                    }
                } else if (!empty($staff_id)) {
                    // If staff is selected, either update or create new assignment
                    $assignment_data = [
                        'assignment_id' => $assignment_id,
                        'staff_id' => $staff_id,
                        'pump_id' => $pump_id,
                        'assignment_date' => $_POST['assignment_date'],
                        'shift' => $shift,
                        'status' => 'assigned',
                        'assigned_by' => $_SESSION['user_id'],
                        'notes' => $_POST['notes'][$pump_id][$shift] ?? null
                    ];
                    
                    $result = assignStaff($conn, $assignment_data);
                    
                    if (!$result) {
                        throw new Exception("Failed to assign staff to pump #$pump_id for $shift shift.");
                    }
                }
            }
        }
        
        // Commit the transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Staff assignments saved successfully.";
        
        // Redirect to prevent resubmission
        header("Location: assign_staff.php?date=" . $_POST['assignment_date']);
        exit;
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        
        $error_message = $e->getMessage();
        $success = false;
    }
}

// Get current assignments for the selected date
$current_assignments = getStaffAssignments($conn, $assignment_date);

// Organize current assignments for easier access
$assigned_staff = [];
foreach ($current_assignments as $assignment) {
    $assigned_staff[$assignment['pump_id']][$assignment['shift']] = [
        'staff_id' => $assignment['staff_id'],
        'status' => $assignment['status'],
        'notes' => $assignment['notes']
    ];
}

// Handle flash messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? $error_message ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!-- Notification Messages -->
<?php if (!empty($success_message)): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
    <p><?= htmlspecialchars($success_message) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
    <p><?= htmlspecialchars($error_message) ?></p>
</div>
<?php endif; ?>

<!-- Date Selection -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form id="date-form" class="flex flex-wrap items-end gap-4">
        <div>
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Assignment Date</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($assignment_date) ?>" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                onchange="document.getElementById('date-form').submit()">
        </div>
        
        <?php if ($selected_staff): ?>
            <input type="hidden" name="staff_id" value="<?= $selected_staff_id ?>">
        <?php endif; ?>
        
        <div class="ml-auto">
            <a href="index.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Back to Dashboard
            </a>
        </div>
    </form>
</div>

<!-- Staff Assignments Form -->
<div class="bg-white rounded-lg shadow">
    <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800">Staff Assignment for <?= date('F d, Y', strtotime($assignment_date)) ?></h2>
        <p class="text-sm text-gray-500 mt-1">Showing only staff who are present (not on leave)</p>
    </div>
    
    <?php if (empty($active_pumps)): ?>
        <div class="p-6 text-center">
            <p class="text-gray-500">No active pumps available for assignment.</p>
        </div>
    <?php elseif (empty($present_staff)): ?>
        <div class="p-6 text-center">
            <p class="text-gray-500">No available staff members for assignment on this date.</p>
        </div>
    <?php else: ?>
        <form action="assign_staff.php" method="post">
            <input type="hidden" name="assignment_date" value="<?= htmlspecialchars($assignment_date) ?>">
            
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                                <?php foreach ($shifts as $shift): ?>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= ucfirst($shift) ?> Shift</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($active_pumps as $pump): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($pump['pump_name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($pump['fuel_name']) ?>
                                        </div>
                                    </td>
                                    
                                    <?php foreach ($shifts as $shift): ?>
                                        <td class="px-4 py-3">
                                            <div class="mb-2">
                                                <select name="assignments[<?= $pump['pump_id'] ?>][<?= $shift ?>]" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Select Staff</option>
                                                    <?php foreach ($present_staff as $staff): ?>
                                                        <?php
                                                        $is_selected = false;
                                                        
                                                        // Check if this staff is already assigned to this pump and shift
                                                        if (isset($assigned_staff[$pump['pump_id']][$shift]) && 
                                                            $assigned_staff[$pump['pump_id']][$shift]['staff_id'] == $staff['staff_id']) {
                                                            $is_selected = true;
                                                        }
                                                        
                                                        // Or if filtering by staff, pre-select the filtered staff
                                                        elseif ($selected_staff && $staff['staff_id'] == $selected_staff['staff_id'] && 
                                                               !isset($assigned_staff[$pump['pump_id']][$shift])) {
                                                            $is_selected = true;
                                                        }
                                                        ?>
                                                        <option value="<?= $staff['staff_id'] ?>" <?= $is_selected ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?> (<?= htmlspecialchars($staff['staff_code']) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div>
                                                <input type="text" name="notes[<?= $pump['pump_id'] ?>][<?= $shift ?>]" 
                                                       placeholder="Notes (optional)"
                                                       value="<?= isset($assigned_staff[$pump['pump_id']][$shift]) ? htmlspecialchars($assigned_staff[$pump['pump_id']][$shift]['notes']) : '' ?>"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Save Assignments
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
// Include the footer
require_once '../../includes/footer.php';

ob_end_flush();
?>