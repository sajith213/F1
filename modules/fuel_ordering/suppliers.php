<?php
/**
 * Fuel Ordering Module - Suppliers Management
 * 
 * This page allows for managing fuel suppliers, including adding, editing, 
 * and viewing suppliers.
 */

// Set page title
$page_title = "Manage Suppliers";

// Set breadcrumbs
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               Manage Suppliers';

// Include header
include_once '../../includes/header.php';

// Include module functions
require_once 'functions.php';

// Check for permissions
if (!in_array($user_data['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Database connection
require_once '../../includes/db.php';

// Initialize variables
$success_message = '';
$error_message = '';
$edit_id = 0;
$supplier_data = [
    'supplier_name' => '',
    'contact_person' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'status' => 'active'
];

// Handle supplier actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
    $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';
    
    // Validate required fields
    if (empty($supplier_name)) {
        $error_message = "Supplier name is required.";
    } elseif (empty($phone)) {
        $error_message = "Phone number is required.";
    } else {
        try {
            // Check if it's an update or a new supplier
            if ($supplier_id > 0) {
                // Update existing supplier
                $sql = "UPDATE suppliers SET 
                            supplier_name = ?, 
                            contact_person = ?, 
                            phone = ?, 
                            email = ?, 
                            address = ?, 
                            status = ?,
                            updated_at = NOW() 
                        WHERE supplier_id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $supplier_name, $contact_person, $phone, $email, $address, $status, $supplier_id);
                
                if ($stmt->execute()) {
                    $success_message = "Supplier updated successfully.";
                } else {
                    $error_message = "Error updating supplier: " . $stmt->error;
                }
            } else {
                // Add new supplier
                $sql = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, status) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $supplier_name, $contact_person, $phone, $email, $address, $status);
                
                if ($stmt->execute()) {
                    $success_message = "Supplier added successfully.";
                } else {
                    $error_message = "Error adding supplier: " . $stmt->error;
                }
            }
        } catch (Exception $e) {
            $error_message = "Error processing supplier: " . $e->getMessage();
        }
    }
}

// Handle supplier edit (GET)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    
    // Get supplier data for editing
    $sql = "SELECT * FROM suppliers WHERE supplier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $supplier_data = $result->fetch_assoc();
    } else {
        $error_message = "Supplier not found.";
        $edit_id = 0;
    }
}

// Handle supplier deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    
    // Check if supplier is in use by any orders
    $check_sql = "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $used_count = $check_result->fetch_assoc()['count'];
    
    if ($used_count > 0) {
        $error_message = "Cannot delete supplier: It is used by {$used_count} purchase orders. Consider marking it as inactive instead.";
    } else {
        // Proceed with deletion
        $delete_sql = "DELETE FROM suppliers WHERE supplier_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Supplier deleted successfully.";
        } else {
            $error_message = "Error deleting supplier: " . $delete_stmt->error;
        }
    }
}

// Get all suppliers for listing
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT * FROM suppliers WHERE 1=1";
$params = array();
$types = "";

// Add filters
if (!empty($filter_status)) {
    $query .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_search)) {
    $search_param = "%{$filter_search}%";
    $query .= " AND (supplier_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

$query .= " ORDER BY supplier_name";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$suppliers_result = $stmt->get_result();

// Get supplier usage statistics
$usage_query = "SELECT s.supplier_id, COUNT(po.po_id) as order_count, 
                SUM(po.total_amount) as total_spent
                FROM suppliers s
                LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
                GROUP BY s.supplier_id";
$usage_result = $conn->query($usage_query);

$supplier_usage = [];
if ($usage_result) {
    while ($row = $usage_result->fetch_assoc()) {
        $supplier_usage[$row['supplier_id']] = [
            'order_count' => $row['order_count'],
            'total_spent' => $row['total_spent'] ?: 0
        ];
    }
}

// Get currency symbol
$currency_symbol = get_currency_symbol();
?>

<!-- Page content -->
<div class="container mx-auto px-4 py-4">
    
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($error_message) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Action buttons -->
    <div class="mb-6 flex flex-wrap gap-3">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
        
        <a href="view_orders.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
            <i class="fas fa-list mr-2"></i> View Orders
        </a>
        
        <a href="#add-supplier-form" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
            <i class="fas fa-plus mr-2"></i> Add New Supplier
        </a>
    </div>
    
    <!-- Filter and search -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form action="" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="w-full md:w-auto flex-grow">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Search supplier name, contact, phone..." 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div class="w-full md:w-auto">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All</option>
                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="suppliers.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    <i class="fas fa-undo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Suppliers list -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-800">Suppliers</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($suppliers_result && $suppliers_result->num_rows > 0): ?>
                        <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($supplier['supplier_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($supplier['contact_person'] ?: '—') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($supplier['phone']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($supplier['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                            <?= htmlspecialchars($supplier['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $supplier['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= ucfirst($supplier['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $orders = isset($supplier_usage[$supplier['supplier_id']]) ? $supplier_usage[$supplier['supplier_id']]['order_count'] : 0;
                                    $total_spent = isset($supplier_usage[$supplier['supplier_id']]) ? $supplier_usage[$supplier['supplier_id']]['total_spent'] : 0;
                                    ?>
                                    <div class="text-sm text-gray-900">
                                        <?= $orders ?> order<?= $orders !== 1 ? 's' : '' ?>
                                    </div>
                                    <?php if ($total_spent > 0): ?>
                                        <div class="text-xs text-gray-500">
                                            <?= $currency_symbol ?><?= number_format($total_spent, 2) ?> total
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center space-x-3">
                                        <a href="?action=edit&id=<?= $supplier['supplier_id'] ?>#add-supplier-form" class="text-blue-600 hover:text-blue-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($orders == 0): ?>
                                            <a href="?action=delete&id=<?= $supplier['supplier_id'] ?>" class="text-red-600 hover:text-red-900" 
                                               onclick="return confirm('Are you sure you want to delete this supplier?')" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400 cursor-not-allowed" title="Cannot delete: Supplier has orders">
                                                <i class="fas fa-trash-alt"></i>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <a href="view_orders.php?supplier=<?= $supplier['supplier_id'] ?>" class="text-green-600 hover:text-green-900" title="View Orders">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No suppliers found matching your criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add/Edit Supplier Form -->
    <div id="add-supplier-form" class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <?= $edit_id > 0 ? 'Edit Supplier' : 'Add New Supplier' ?>
        </h2>
        
        <form action="" method="post">
            <input type="hidden" name="supplier_id" value="<?= $edit_id ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Supplier Name -->
                <div>
                    <label for="supplier_name" class="block text-sm font-medium text-gray-700 mb-1">Supplier Name <span class="text-red-500">*</span></label>
                    <input type="text" id="supplier_name" name="supplier_name" value="<?= htmlspecialchars($supplier_data['supplier_name']) ?>" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Contact Person -->
                <div>
                    <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($supplier_data['contact_person']) ?>"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($supplier_data['phone']) ?>" required
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($supplier_data['email']) ?>"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Address -->
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" name="address" rows="3"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= htmlspecialchars($supplier_data['address']) ?></textarea>
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="active" <?= $supplier_data['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $supplier_data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <a href="suppliers.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    <?= $edit_id > 0 ? 'Update Supplier' : 'Add Supplier' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>