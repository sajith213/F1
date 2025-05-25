<?php
/**
 * Suppliers Management
 * 
 * This file handles the management of fuel suppliers including listing, 
 * adding, editing, and deleting supplier records.
 */
$page_title = "Supplier Management";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Purchase Orders</a> / <span class="text-gray-700">Suppliers</span>';
include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check permission
if (!has_permission('manage_suppliers')) {
    header("Location: ../../index.php");
    exit;
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle supplier deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $supplier_id = intval($_GET['id']);
    
    // Check if supplier has associated purchase orders
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?");
    $check_stmt->bind_param("i", $supplier_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_count);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_count > 0) {
        $error_message = "Cannot delete supplier. There are purchase orders associated with this supplier.";
    } else {
        // Delete supplier
        $delete_stmt = $conn->prepare("UPDATE suppliers SET status = 'inactive' WHERE supplier_id = ?");
        $delete_stmt->bind_param("i", $supplier_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Supplier has been marked as inactive.";
        } else {
            $error_message = "Error deactivating supplier: " . $conn->error;
        }
        $delete_stmt->close();
    }
}

// Handle supplier form submission (add/edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Extract and sanitize form data
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';
    
    // Validate required fields
    if (empty($supplier_name) || empty($phone)) {
        $error_message = "Supplier name and phone number are required.";
    } else {
        // Begin transaction for data integrity
        $conn->begin_transaction();
        
        try {
            if ($supplier_id > 0) {
                // Update existing supplier
                $stmt = $conn->prepare("
                    UPDATE suppliers 
                    SET supplier_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE supplier_id = ?
                ");
                $stmt->bind_param("ssssssi", $supplier_name, $contact_person, $phone, $email, $address, $status, $supplier_id);
                
                if ($stmt->execute()) {
                    $success_message = "Supplier updated successfully.";
                } else {
                    throw new Exception("Error updating supplier: " . $conn->error);
                }
            } else {
                // Add new supplier
                $stmt = $conn->prepare("
                    INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssss", $supplier_name, $contact_person, $phone, $email, $address, $status);
                
                if ($stmt->execute()) {
                    $supplier_id = $conn->insert_id;
                    $success_message = "Supplier added successfully.";
                } else {
                    throw new Exception("Error adding supplier: " . $conn->error);
                }
            }
            
            $stmt->close();
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

// Get supplier for editing (if edit mode)
$edit_supplier = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $supplier_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_supplier = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Get all active suppliers for listing
$suppliers = [];
$stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = s.supplier_id) as order_count
    FROM suppliers s
    ORDER BY s.status ASC, s.supplier_name ASC
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

$stmt->close();
?>

<div class="container mx-auto pb-6">
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
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Supplier Form -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <?= isset($edit_supplier) ? 'Edit Supplier' : 'Add New Supplier' ?>
                    </h2>
                </div>
                
                <form method="POST" action="" class="p-6 space-y-4">
                    <?php if (isset($edit_supplier)): ?>
                    <input type="hidden" name="supplier_id" value="<?= $edit_supplier['supplier_id'] ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label for="supplier_name" class="block text-sm font-medium text-gray-700">Supplier Name <span class="text-red-500">*</span></label>
                        <input type="text" id="supplier_name" name="supplier_name" 
                               value="<?= isset($edit_supplier) ? htmlspecialchars($edit_supplier['supplier_name']) : '' ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" 
                               value="<?= isset($edit_supplier) ? htmlspecialchars($edit_supplier['contact_person']) : '' ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number <span class="text-red-500">*</span></label>
                        <input type="text" id="phone" name="phone" 
                               value="<?= isset($edit_supplier) ? htmlspecialchars($edit_supplier['phone']) : '' ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?= isset($edit_supplier) ? htmlspecialchars($edit_supplier['email']) : '' ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea id="address" name="address" rows="3" 
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= isset($edit_supplier) ? htmlspecialchars($edit_supplier['address']) : '' ?></textarea>
                    </div>
                    
                    <?php if (isset($edit_supplier)): ?>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="active" <?= $edit_supplier['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $edit_supplier['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-end space-x-3 mt-5">
                        <?php if (isset($edit_supplier)): ?>
                        <a href="suppliers.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </a>
                        <?php endif; ?>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <?= isset($edit_supplier) ? 'Update Supplier' : 'Add Supplier' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Suppliers List -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Suppliers List</h2>
                    <input type="text" id="supplier-search" placeholder="Search suppliers..." class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 w-64">
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="suppliers-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No suppliers found.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                <tr class="supplier-row <?= $supplier['status'] == 'inactive' ? 'bg-gray-50' : '' ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($supplier['supplier_name']) ?></div>
                                        <?php if ($supplier['email']): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($supplier['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($supplier['contact_person'] ?: '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($supplier['phone']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $supplier['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= ucfirst($supplier['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $supplier['order_count'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="suppliers.php?action=edit&id=<?= $supplier['supplier_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($supplier['order_count'] == 0 && $supplier['status'] == 'active'): ?>
                                        <a href="suppliers.php?action=delete&id=<?= $supplier['supplier_id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to mark this supplier as inactive?');">
                                            <i class="fas fa-trash"></i> Deactivate
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple search functionality for suppliers
    const searchInput = document.getElementById('supplier-search');
    const supplierRows = document.querySelectorAll('.supplier-row');
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        
        supplierRows.forEach(row => {
            const supplierName = row.querySelector('td:first-child').textContent.toLowerCase();
            const contactPerson = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const phone = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            
            if (supplierName.includes(searchTerm) || 
                contactPerson.includes(searchTerm) || 
                phone.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>