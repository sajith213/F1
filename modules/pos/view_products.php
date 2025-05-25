<?php
/**
 * POS Module - View Products
 * 
 * This file displays a list of products with search, filter, and pagination
 */

// Set page title and include header
$page_title = "Product Inventory";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Point of Sale</a> / <span class="text-gray-700">Product Inventory</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';

// Initialize variables for filtering and pagination
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$filter = $_GET['filter'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($conn) && defined('RECORDS_PER_PAGE') ? RECORDS_PER_PAGE : 10;
$offset = ($page - 1) * $per_page;

// Base query for products
$baseQuery = "
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.category_id
    WHERE 1=1
";

$countQuery = "SELECT COUNT(*) FROM products p WHERE 1=1";
$params = [];
$types = "";

// Apply search filter
if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $baseQuery .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.description LIKE ?)";
    $countQuery .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.description LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Apply category filter
if (!empty($category)) {
    $baseQuery .= " AND p.category_id = ?";
    $countQuery .= " AND p.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

// Apply status filter
if (!empty($status)) {
    $baseQuery .= " AND p.status = ?";
    $countQuery .= " AND p.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Apply special filters
if ($filter === 'low_stock') {
    $baseQuery .= " AND p.current_stock <= p.reorder_level";
    $countQuery .= " AND p.current_stock <= p.reorder_level";
} elseif ($filter === 'out_of_stock') {
    $baseQuery .= " AND p.current_stock = 0";
    $countQuery .= " AND p.current_stock = 0";
}

// Add sorting
$baseQuery .= " ORDER BY p.product_name ASC";

// Add pagination
$baseQuery .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Initialize variables for results
$total_records = 0;
$total_pages = 1;
$products = [];

// Execute database queries if connection exists
if (!isset($conn) || !$conn) {
    $error_message = "Database connection not available";
} else {
    // Prepare and execute query for total count
    $countStmt = $conn->prepare($countQuery);
    if ($countStmt) {
        // Remove the last two params which are for pagination
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($types, 0, -2);
        
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $result = $countStmt->get_result();
        $total_records = $result->fetch_row()[0];
        $total_pages = ceil($total_records / $per_page);
        $countStmt->close();
    }

    // Get all categories for filter dropdown
    $categories = [];
    $categoryQuery = "SELECT category_id, category_name FROM product_categories WHERE status = 'active' ORDER BY category_name";
    $result = $conn->query($categoryQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    // Execute main query with pagination
    if ($total_records > 0) {
        $stmt = $conn->prepare($baseQuery);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            $stmt->close();
        }
    }
}

// Check if we need to show a success message
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// Get currency symbol from settings
$currency_symbol = 'LKR';
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currency_symbol = $row['setting_value'];
    }
    $stmt->close();
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Action buttons and title row -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Product Inventory Management</h2>
        
        <div class="flex gap-2">
            <a href="add_product.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>
                Add New Product
            </a>
        </div>
    </div>
    
    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Filters and search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search box -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Products</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                    placeholder="Search by name, code, or description" 
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <!-- Category filter -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category" name="category" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= $category == $cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Status filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>
            
            <!-- Special filters -->
            <div>
                <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Special Filters</label>
                <select id="filter" name="filter" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">No Filter</option>
                    <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </div>
            
            <!-- Search button -->
            <div class="flex items-end md:col-span-2 lg:col-span-4 justify-end space-x-3">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="view_products.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Products table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-xl font-semibold text-gray-800">
                Product List
                <?php if ($total_records > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($total_records) ?> products)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="p-6 text-center text-gray-500">
                No products found. Try adjusting your search criteria or 
                <a href="add_product.php" class="text-blue-600 hover:text-blue-800">add a new product</a>.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selling Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($product['product_code']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($product['product_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $currency_symbol ?> <?= number_format($product['selling_price'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php 
                                $stockClass = 'text-green-600';
                                if ($product['current_stock'] <= $product['reorder_level']) {
                                    $stockClass = 'text-orange-600';
                                }
                                if ($product['current_stock'] == 0) {
                                    $stockClass = 'text-red-600 font-bold';
                                }
                                ?>
                                <span class="<?= $stockClass ?>">
                                    <?= $product['current_stock'] ?>
                                </span>
                                <span class="text-gray-500 text-xs ml-1">
                                    (Min: <?= $product['reorder_level'] ?>)
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php 
                                $statusBadge = '';
                                switch ($product['status']) {
                                    case 'active':
                                        $statusBadge = 'bg-green-100 text-green-800';
                                        break;
                                    case 'inactive':
                                        $statusBadge = 'bg-gray-100 text-gray-800';
                                        break;
                                    case 'out_of_stock':
                                        $statusBadge = 'bg-red-100 text-red-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusBadge ?>">
                                    <?= ucfirst(str_replace('_', ' ', $product['status'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="update_product.php?id=<?= $product['product_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="javascript:void(0)" onclick="confirmDelete(<?= $product['product_id'] ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> products
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&status=<?= $status ?>&filter=<?= $filter ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&status=<?= $status ?>&filter=<?= $filter ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        window.location.href = 'delete_product.php?id=' + productId;
    }
}
</script>

<?php include_once '../../includes/footer.php'; ?>