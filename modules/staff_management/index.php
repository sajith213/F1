<?php
/**
 * Staff Management Dashboard
 * 
 * Main dashboard page for staff management module
 */

// Set page title and load header
$page_title = "Staff Management";
$breadcrumbs = "<a href='../../index.php'>Home</a> / Staff Management";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Get staff statistics
$total_staff = 0;
$active_staff = 0;
$on_leave_staff = 0;
$inactive_staff = 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_staff = $row['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$active_staff = $row['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE status = 'on_leave'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$on_leave_staff = $row['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE status = 'inactive'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$inactive_staff = $row['total'];

// Get the latest 5 staff additions
$recent_staff = [];
$stmt = $conn->prepare("
    SELECT * FROM staff 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_staff[] = $row;
}

// Get today's staff assignments
$today = date('Y-m-d');
$today_assignments = getStaffAssignments($conn, $today);

// Handle flash messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
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

<!-- Quick Actions -->
<div class="flex flex-wrap mb-6 gap-4">
    <a href="add_staff.php" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Add New Staff
    </a>
    <a href="view_staff.php" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
        <i class="fas fa-list mr-2"></i> View All Staff
    </a>
    <a href="assign_staff.php" class="flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
        <i class="fas fa-tasks mr-2"></i> Assign Staff to Pumps
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3 mr-4">
                <i class="fas fa-users text-blue-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Total Staff</p>
                <p class="text-2xl font-bold"><?= $total_staff ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3 mr-4">
                <i class="fas fa-user-check text-green-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Active Staff</p>
                <p class="text-2xl font-bold"><?= $active_staff ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-yellow-100 p-3 mr-4">
                <i class="fas fa-umbrella-beach text-yellow-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">On Leave</p>
                <p class="text-2xl font-bold"><?= $on_leave_staff ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-red-100 p-3 mr-4">
                <i class="fas fa-user-slash text-red-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Inactive Staff</p>
                <p class="text-2xl font-bold"><?= $inactive_staff ?></p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recently Added Staff -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-lg font-semibold text-gray-700">Recently Added Staff</h2>
        </div>
        <div class="p-4">
            <?php if (empty($recent_staff)): ?>
                <p class="text-gray-500 text-center py-4">No staff records found.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hire Date</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_staff as $staff): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-800 font-bold">
                                                <?= strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($staff['staff_code']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($staff['position']) ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php
                                        $status_colors = [
                                            'active' => 'bg-green-100 text-green-800',
                                            'inactive' => 'bg-red-100 text-red-800',
                                            'on_leave' => 'bg-yellow-100 text-yellow-800',
                                            'terminated' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $status_color = $status_colors[$staff['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                            <?= ucfirst(str_replace('_', ' ', $staff['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($staff['hire_date'])) ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center text-sm">
                                        <a href="staff_details.php?id=<?= $staff['staff_id'] ?>" class="text-blue-600 hover:text-blue-900 mx-1">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="update_staff.php?id=<?= $staff['staff_id'] ?>" class="text-green-600 hover:text-green-900 mx-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-center">
                    <a href="view_staff.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View All Staff <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Today's Staff Assignments -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-lg font-semibold text-gray-700">Today's Staff Assignments</h2>
        </div>
        <div class="p-4">
            <?php if (empty($today_assignments)): ?>
                <p class="text-gray-500 text-center py-4">No assignments for today.</p>
                <div class="mt-4 text-center">
                    <a href="assign_staff.php" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
                        <i class="fas fa-plus mr-2"></i> Create Assignments
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($today_assignments as $assignment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-800 font-bold">
                                                <?= strtoupper(substr($assignment['first_name'], 0, 1) . substr($assignment['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($assignment['staff_code']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($assignment['pump_name']) ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        <?= ucfirst($assignment['shift']) ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php
                                        $status_colors = [
                                            'assigned' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'absent' => 'bg-red-100 text-red-800',
                                            'reassigned' => 'bg-yellow-100 text-yellow-800'
                                        ];
                                        $status_color = $status_colors[$assignment['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                            <?= ucfirst($assignment['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-center">
                    <a href="assign_staff.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Manage Assignments <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include the footer
require_once '../../includes/footer.php';
?>