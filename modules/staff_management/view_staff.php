<?php
/**
 * View Staff
 * 
 * List and search all staff members
 */

// Set page title and load header
$page_title = "View Staff";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Staff Management</a> / View Staff";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Handle delete action if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $staff_id = (int)$_GET['id'];
    
    // Confirm the staff exists
    $staff = getStaffById($conn, $staff_id);
    
    if ($staff) {
        if (deleteStaff($conn, $staff_id)) {
            $_SESSION['success_message'] = "Staff member deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting staff member. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Staff member not found.";
    }
    
    // Redirect to avoid resubmission on refresh
    header("Location: view_staff.php");
    exit;
}

// Process search and filters
$filters = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['position']) && !empty($_GET['position'])) {
    $filters['position'] = $_GET['position'];
}

// Get staff list with filters
$staff_list = getAllStaff($conn, $filters);

// Get distinct positions for filter dropdown
$positions = [];
$stmt = $conn->prepare("SELECT DISTINCT position FROM staff ORDER BY position");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $positions[] = $row['position'];
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

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form action="view_staff.php" method="get" class="flex flex-wrap items-end gap-4">
        <!-- Search -->
        <div class="flex-grow min-w-[200px]">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                placeholder="Name, Staff Code, Phone..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <!-- Status Filter -->
        <div class="w-full md:w-auto">
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Statuses</option>
                <option value="active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="on_leave" <?= isset($_GET['status']) && $_GET['status'] === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                <option value="terminated" <?= isset($_GET['status']) && $_GET['status'] === 'terminated' ? 'selected' : '' ?>>Terminated</option>
            </select>
        </div>
        
        <!-- Position Filter -->
        <div class="w-full md:w-auto">
            <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position</label>
            <select id="position" name="position" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Positions</option>
                <?php foreach ($positions as $position): ?>
                    <option value="<?= htmlspecialchars($position) ?>" <?= isset($_GET['position']) && $_GET['position'] === $position ? 'selected' : '' ?>>
                        <?= htmlspecialchars($position) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Submit Button -->
        <div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-search mr-1"></i> Filter
            </button>
        </div>
        
        <!-- Reset Button - Only show if filters are applied -->
        <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['position'])): ?>
        <div>
            <a href="view_staff.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Staff List -->
<div class="bg-white rounded-lg shadow">
    <div class="flex justify-between items-center p-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-700">Staff List</h2>
        <a href="add_staff.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            <i class="fas fa-plus mr-1"></i> Add New Staff
        </a>
    </div>
    
    <div class="p-4">
        <?php if (empty($staff_list)): ?>
            <div class="text-center py-6">
                <i class="fas fa-users text-gray-300 text-5xl mb-3"></i>
                <p class="text-gray-500">No staff members found matching your criteria.</p>
                <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['position'])): ?>
                    <a href="view_staff.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hire Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($staff_list as $staff): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-800 font-bold">
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
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($staff['position']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($staff['department'] ?? 'N/A') ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($staff['phone']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($staff['email'] ?? 'N/A') ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
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
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($staff['hire_date'])) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="staff_details.php?id=<?= $staff['staff_id'] ?>" class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="update_staff.php?id=<?= $staff['staff_id'] ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="text-red-600 hover:text-red-900 delete-staff" 
                                           data-id="<?= $staff['staff_id'] ?>" 
                                           data-name="<?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Records count -->
            <div class="mt-4 text-sm text-gray-500">
                Showing <?= count($staff_list) ?> staff members
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="hidden fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div id="modal-backdrop" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        
        <!-- Modal content -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Delete Staff Member
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="modal-message">
                                Are you sure you want to delete this staff member? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a href="#" id="confirm-delete" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </a>
                <button type="button" id="cancel-delete" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for delete confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete confirmation
    const deleteModal = document.getElementById('delete-modal');
    const modalMessage = document.getElementById('modal-message');
    const confirmDelete = document.getElementById('confirm-delete');
    const cancelDelete = document.getElementById('cancel-delete');
    const modalBackdrop = document.getElementById('modal-backdrop');
    
    // Add event listeners to all delete buttons
    document.querySelectorAll('.delete-staff').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const staffId = this.getAttribute('data-id');
            const staffName = this.getAttribute('data-name');
            
            // Set the confirmation message and delete link
            modalMessage.textContent = `Are you sure you want to delete ${staffName}? This action cannot be undone.`;
            confirmDelete.href = `view_staff.php?action=delete&id=${staffId}`;
            
            // Show the modal
            deleteModal.classList.remove('hidden');
        });
    });
    
    // Close modal when Cancel is clicked
    cancelDelete.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
    });
    
    // Close modal when clicking outside
    modalBackdrop.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
    });
});
</script>

<?php
// Include the footer
require_once '../../includes/footer.php';
?>