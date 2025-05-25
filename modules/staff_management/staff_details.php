<?php
/**
 * Staff Details
 * 
 * Show detailed information about a staff member
 */

// Set page title and load header
$page_title = "Staff Details";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check if staff ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required.";
    header("Location: index.php");
    exit;
}

$staff_id = (int)$_GET['id'];
$staff = getStaffById($conn, $staff_id);

// If staff not found, redirect to staff list
if (!$staff) {
    $_SESSION['error_message'] = "Staff member not found.";
    header("Location: index.php");
    exit;
}

// Update breadcrumbs
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Staff Management</a> / " . 
               htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']);

// Get user info if this staff has a user account
$user_info = null;
if (!empty($staff['user_id'])) {
    $stmt = $conn->prepare("SELECT username, role, status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $staff['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_info = $result->fetch_assoc();
    }
}

// Get recent assignments
$stmt = $conn->prepare("
    SELECT sa.*, p.pump_name, s.first_name, s.last_name 
    FROM staff_assignments sa
    JOIN pumps p ON sa.pump_id = p.pump_id
    JOIN staff s ON sa.assigned_by = s.staff_id
    WHERE sa.staff_id = ?
    ORDER BY sa.assignment_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_assignments = [];
while ($row = $result->fetch_assoc()) {
    $recent_assignments[] = $row;
}

// Get performance metrics
$performance_data = getStaffPerformance($conn, $staff_id);

// Get recent attendance records
$stmt = $conn->prepare("
    SELECT * FROM attendance_records
    WHERE staff_id = ?
    ORDER BY attendance_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_attendance = [];
while ($row = $result->fetch_assoc()) {
    $recent_attendance[] = $row;
}

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

<!-- Action Buttons -->
<div class="flex flex-wrap gap-3 mb-6">
    <a href="view_staff.php" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
        <i class="fas fa-arrow-left mr-2"></i> Back to Staff List
    </a>
    <a href="update_staff.php?id=<?= $staff_id ?>" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
        <i class="fas fa-edit mr-2"></i> Edit Staff
    </a>
    <a href="assign_staff.php?staff_id=<?= $staff_id ?>" class="flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
        <i class="fas fa-tasks mr-2"></i> Assign to Pump
    </a>
</div>

<!-- Staff Details -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Info Card -->
    <div class="bg-white rounded-lg shadow lg:col-span-2">
        <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Staff Information</h2>
                
                <!-- Status Badge -->
                <?php
                $status_colors = [
                    'active' => 'bg-green-100 text-green-800',
                    'inactive' => 'bg-red-100 text-red-800',
                    'on_leave' => 'bg-yellow-100 text-yellow-800',
                    'terminated' => 'bg-gray-100 text-gray-800'
                ];
                $status_color = $status_colors[$staff['status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?= $status_color ?>">
                    <?= ucfirst(str_replace('_', ' ', $staff['status'])) ?>
                </span>
            </div>
        </div>
        
        <div class="p-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-start mb-6">
                <!-- Profile Image/Initials -->
                <div class="h-24 w-24 bg-blue-100 rounded-full flex items-center justify-center text-blue-800 text-4xl font-bold mb-4 sm:mb-0 sm:mr-6">
                    <?= strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)) ?>
                </div>
                
                <!-- Basic Info -->
                <div>
                    <h3 class="text-2xl font-semibold text-gray-800">
                        <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                    </h3>
                    <p class="text-gray-600">
                        <span class="font-medium"><?= htmlspecialchars($staff['position']) ?></span>
                        <?php if (!empty($staff['department'])): ?>
                            <span class="mx-2">•</span> <?= htmlspecialchars($staff['department']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="text-gray-500 mt-1">Staff Code: <?= htmlspecialchars($staff['staff_code']) ?></p>
                    <p class="text-gray-500">Joined: <?= date('F d, Y', strtotime($staff['hire_date'])) ?></p>
                </div>
            </div>
            
            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mt-6">
                <!-- Contact Information -->
                <div>
                    <h4 class="text-sm uppercase tracking-wider text-gray-500 font-medium mb-3">Contact Information</h4>
                    
                    <div class="mb-3">
                        <span class="block text-sm font-medium text-gray-700">Phone</span>
                        <span class="block text-base text-gray-900"><?= htmlspecialchars($staff['phone']) ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <span class="block text-sm font-medium text-gray-700">Email</span>
                        <span class="block text-base text-gray-900">
                            <?= !empty($staff['email']) ? htmlspecialchars($staff['email']) : 'Not provided' ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <span class="block text-sm font-medium text-gray-700">Address</span>
                        <span class="block text-base text-gray-900">
                            <?= !empty($staff['address']) ? nl2br(htmlspecialchars($staff['address'])) : 'Not provided' ?>
                        </span>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div>
                    <h4 class="text-sm uppercase tracking-wider text-gray-500 font-medium mb-3">Personal Information</h4>
                    
                    <div class="mb-3">
                        <span class="block text-sm font-medium text-gray-700">Gender</span>
                        <span class="block text-base text-gray-900"><?= ucfirst($staff['gender']) ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <span class="block text-sm font-medium text-gray-700">Date of Birth</span>
                        <span class="block text-base text-gray-900">
                            <?= !empty($staff['date_of_birth']) ? date('F d, Y', strtotime($staff['date_of_birth'])) : 'Not provided' ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <span class="block text-sm font-medium text-gray-700">Emergency Contact</span>
                        <?php if (!empty($staff['emergency_contact_name']) && !empty($staff['emergency_contact_phone'])): ?>
                            <span class="block text-base text-gray-900">
                                <?= htmlspecialchars($staff['emergency_contact_name']) ?> (<?= htmlspecialchars($staff['emergency_contact_phone']) ?>)
                            </span>
                        <?php else: ?>
                            <span class="block text-base text-gray-900">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- User Account Information (if exists) -->
            <?php if ($user_info): ?>
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h4 class="text-sm uppercase tracking-wider text-gray-500 font-medium mb-3">System Account</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="block text-sm font-medium text-gray-700">Username</span>
                            <span class="block text-base text-gray-900"><?= htmlspecialchars($user_info['username']) ?></span>
                        </div>
                        
                        <div>
                            <span class="block text-sm font-medium text-gray-700">Role</span>
                            <span class="block text-base text-gray-900 capitalize"><?= htmlspecialchars($user_info['role']) ?></span>
                        </div>
                        
                        <div>
                            <span class="block text-sm font-medium text-gray-700">Account Status</span>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $user_info['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= ucfirst($user_info['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Notes (if any) -->
            <?php if (!empty($staff['notes'])): ?>
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h4 class="text-sm uppercase tracking-wider text-gray-500 font-medium mb-3">Additional Notes</h4>
                    <div class="text-gray-700 whitespace-pre-line"><?= nl2br(htmlspecialchars($staff['notes'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Salary Management Section -->
<?php if (has_permission('manage_salaries') || has_permission('calculate_salaries') || has_permission('view_salary_reports') || has_permission('manage_loans')): ?>
<div class="bg-white rounded-lg shadow lg:col-span-2 mb-6">
    <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800">Salary Management</h2>
    </div>
    
    <div class="p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <?php if (has_permission('manage_salaries')): ?>
            <a href="<?= $base_url ?>modules/salary/employee_salary.php?staff_id=<?= $staff_id ?>" 
               class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                <div class="p-3 bg-blue-100 rounded-full mr-3">
                    <i class="fas fa-cog text-blue-600"></i>
                </div>
                <div>
                    <div class="font-medium text-blue-900">Salary Settings</div>
                    <div class="text-xs text-blue-600">Configure salary components</div>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if (has_permission('calculate_salaries')): ?>
            <a href="<?= $base_url ?>modules/salary/salary_calculator.php?staff_id=<?= $staff_id ?>" 
               class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                <div class="p-3 bg-green-100 rounded-full mr-3">
                    <i class="fas fa-calculator text-green-600"></i>
                </div>
                <div>
                    <div class="font-medium text-green-900">Calculate Salary</div>
                    <div class="text-xs text-green-600">Process monthly salary</div>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if (has_permission('manage_loans')): ?>
            <a href="<?= $base_url ?>modules/salary/loans.php?staff_id=<?= $staff_id ?>" 
               class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                <div class="p-3 bg-purple-100 rounded-full mr-3">
                    <i class="fas fa-hand-holding-usd text-purple-600"></i>
                </div>
                <div>
                    <div class="font-medium text-purple-900">Loans & Advances</div>
                    <div class="text-xs text-purple-600">Manage employee loans</div>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if (has_permission('view_salary_reports')): ?>
            <a href="<?= $base_url ?>modules/salary/payslip.php?staff_id=<?= $staff_id ?>" 
               class="flex items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                <div class="p-3 bg-yellow-100 rounded-full mr-3">
                    <i class="fas fa-file-invoice-dollar text-yellow-600"></i>
                </div>
                <div>
                    <div class="font-medium text-yellow-900">Payslips</div>
                    <div class="text-xs text-yellow-600">Generate & view payslips</div>
                </div>
            </a>
            
            <a href="<?= $base_url ?>modules/salary/salary_report.php?staff_id=<?= $staff_id ?>" 
               class="flex items-center p-4 bg-red-50 rounded-lg hover:bg-red-100 transition">
                <div class="p-3 bg-red-100 rounded-full mr-3">
                    <i class="fas fa-chart-bar text-red-600"></i>
                </div>
                <div>
                    <div class="font-medium text-red-900">Salary History</div>
                    <div class="text-xs text-red-600">View payment history</div>
                </div>
            </a>
            
            <a href="<?= $base_url ?>modules/salary/epf_etf_report.php?staff_id=<?= $staff_id ?>" 
               class="flex items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                <div class="p-3 bg-indigo-100 rounded-full mr-3">
                    <i class="fas fa-file-contract text-indigo-600"></i>
                </div>
                <div>
                    <div class="font-medium text-indigo-900">EPF/ETF Reports</div>
                    <div class="text-xs text-indigo-600">Retirement contributions</div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
    <!-- Sidebar Cards -->
    <div class="space-y-6">
        <!-- Recent Assignments Card -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200 px-4 py-3">
                <h3 class="text-lg font-semibold text-gray-700">Recent Assignments</h3>
            </div>
            <div class="p-4">
                <?php if (empty($recent_assignments)): ?>
                    <p class="text-gray-500 text-center py-4">No recent assignments found.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_assignments as $assignment): ?>
                            <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($assignment['pump_name']) ?>
                                        </span>
                                        <span class="block text-xs text-gray-500">
                                            <?= date('M d, Y', strtotime($assignment['assignment_date'])) ?> • <?= ucfirst($assignment['shift']) ?> Shift
                                        </span>
                                    </div>
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
                                </div>
                                <?php if (!empty($assignment['notes'])): ?>
                                    <p class="text-xs text-gray-500 mt-1 italic">
                                        "<?= htmlspecialchars($assignment['notes']) ?>"
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="assign_staff.php?staff_id=<?= $staff_id ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Manage Assignments <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Attendance Card -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200 px-4 py-3">
                <h3 class="text-lg font-semibold text-gray-700">Recent Attendance</h3>
            </div>
            <div class="p-4">
                <?php if (empty($recent_attendance)): ?>
                    <p class="text-gray-500 text-center py-4">No attendance records found.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_attendance as $attendance): ?>
                            <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= date('M d, Y', strtotime($attendance['attendance_date'])) ?>
                                    </span>
                                    <?php
                                    $status_colors = [
                                        'present' => 'bg-green-100 text-green-800',
                                        'absent' => 'bg-red-100 text-red-800',
                                        'late' => 'bg-yellow-100 text-yellow-800',
                                        'half_day' => 'bg-orange-100 text-orange-800',
                                        'leave' => 'bg-blue-100 text-blue-800'
                                    ];
                                    $status_color = $status_colors[$attendance['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($attendance['status']) ?>
                                    </span>
                                </div>
                                <?php if (!empty($attendance['time_in']) && !empty($attendance['time_out'])): ?>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>
                                            In: <?= date('h:i A', strtotime($attendance['time_in'])) ?>
                                        </span>
                                        <span>
                                            Out: <?= date('h:i A', strtotime($attendance['time_out'])) ?>
                                        </span>
                                        <span>
                                            Hours: <?= number_format($attendance['hours_worked'], 2) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="../attendance/view_attendance.php?staff_id=<?= $staff_id ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Full Attendance <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Performance Metrics Card -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200 px-4 py-3">
                <h3 class="text-lg font-semibold text-gray-700">Performance Metrics</h3>
            </div>
            <div class="p-4">
                <?php if (empty($performance_data)): ?>
                    <p class="text-gray-500 text-center py-4">No performance data available.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($performance_data as $performance): ?>
                            <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= date('M d, Y', strtotime($performance['evaluation_date'])) ?>
                                    </span>
                                    <div class="flex">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $performance['rating']): ?>
                                                <i class="fas fa-star text-yellow-400"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-gray-300"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>
                                        Sales: <?= number_format($performance['sales_amount'], 2) ?> <?= CURRENCY_SYMBOL ?>
                                    </span>
                                    <span>
                                        Customers: <?= $performance['customers_served'] ?>
                                    </span>
                                </div>
                                <?php if (!empty($performance['comments'])): ?>
                                    <p class="text-xs text-gray-500 mt-1 italic">
                                        "<?= htmlspecialchars($performance['comments']) ?>"
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include the footer
require_once '../../includes/footer.php';
?>