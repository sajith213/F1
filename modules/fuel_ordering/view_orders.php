<?php
/**
 * Fuel Ordering Module - View Orders
 * 
 * This page displays a list of all purchase orders with filtering,
 * sorting and pagination capabilities.
 */

// Set page title
$page_title = "View Orders";

// Set breadcrumbs
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / 
               <a href="index.php" class="text-blue-600 hover:text-blue-800">Fuel Ordering</a> / 
               View Orders';

// Include header
include_once '../../includes/header.php';

// Include module functions
require_once 'functions.php';

// Check for permissions
if (!in_array($user_data['role'], ['admin', 'manager', 'cashier'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Database connection
require_once '../../includes/db.php';

// Get currency symbol
$currency_symbol = get_currency_symbol();

// Initialize filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_payment = isset($_GET['payment']) ? $_GET['payment'] : '';
$filter_supplier = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$filter_fuel_type = isset($_GET['fuel_type']) ? intval($_GET['fuel_type']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Initialize pagination variables
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the SQL query - Modified to include fuel type filtering
$sql = "SELECT DISTINCT po.po_id, po.po_number, po.order_date, po.expected_delivery_date, 
        po.status, po.total_amount, po.payment_status, po.payment_date,
        s.supplier_name, u.full_name as created_by
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN users u ON po.created_by = u.user_id";

$count_sql = "SELECT COUNT(DISTINCT po.po_id) as total FROM purchase_orders po 
             LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id";

// Add fuel type join if filter is applied
if ($filter_fuel_type > 0) {
    $sql .= " INNER JOIN po_items poi ON po.po_id = poi.po_id";
    $count_sql .= " INNER JOIN po_items poi ON po.po_id = poi.po_id";
}

$sql .= " WHERE 1=1 ";
$count_sql .= " WHERE 1=1 ";

$params = array();
$types = "";

// Add filters to query
if (!empty($filter_status)) {
    $sql .= "AND po.status = ? ";
    $count_sql .= "AND po.status = ? ";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_payment)) {
    $sql .= "AND po.payment_status = ? ";
    $count_sql .= "AND po.payment_status = ? ";
    $params[] = $filter_payment;
    $types .= "s";
}

if ($filter_supplier > 0) {
    $sql .= "AND po.supplier_id = ? ";
    $count_sql .= "AND po.supplier_id = ? ";
    $params[] = $filter_supplier;
    $types .= "i";
}

// Add fuel type filter
if ($filter_fuel_type > 0) {
    $sql .= "AND poi.fuel_type_id = ? ";
    $count_sql .= "AND poi.fuel_type_id = ? ";
    $params[] = $filter_fuel_type;
    $types .= "i";
}

if (!empty($filter_date_from)) {
    $sql .= "AND po.order_date >= ? ";
    $count_sql .= "AND po.order_date >= ? ";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $sql .= "AND po.order_date <= ? ";
    $count_sql .= "AND po.order_date <= ? ";
    $params[] = $filter_date_to;
    $types .= "s";
}

if (!empty($filter_search)) {
    $search_param = "%{$filter_search}%";
    $sql .= "AND (po.po_number LIKE ? OR s.supplier_name LIKE ?) ";
    $count_sql .= "AND (po.po_number LIKE ? OR s.supplier_name LIKE ?) ";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add sorting
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_direction = isset($_GET['dir']) && $_GET['dir'] == 'asc' ? 'ASC' : 'DESC';

// Validate sort field to prevent SQL injection
$allowed_sort_fields = ['po_number', 'order_date', 'status', 'total_amount', 'payment_status', 'supplier_name'];
if (!in_array($sort_field, $allowed_sort_fields)) {
    $sort_field = 'order_date';
}

$sql .= "ORDER BY po.{$sort_field} {$sort_direction} ";

// Add pagination
$sql .= "LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute count query to get total records
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    // Bind parameters to count query (exclude pagination params)
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    if (!empty($count_params)) {
        $ref_array = array();
        $ref_array[] = &$count_types;
        for ($i = 0; $i < count($count_params); $i++) {
            $ref_array[] = &$count_params[$i];
        }
        call_user_func_array(array($count_stmt, 'bind_param'), $ref_array);
    }
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Prepare and execute main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $ref_array = array();
    $ref_array[] = &$types;
    for ($i = 0; $i < count($params); $i++) {
        $ref_array[] = &$params[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $ref_array);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all suppliers for filter dropdown
$suppliers_query = "SELECT supplier_id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Get all fuel types for filter dropdown
$fuel_types_query = "SELECT fuel_type_id, fuel_name FROM fuel_types ORDER BY fuel_name";
$fuel_types_result = $conn->query($fuel_types_query);

// Status colors for badges
$status_classes = [
    'draft' => 'bg-gray-100 text-gray-800',
    'submitted' => 'bg-blue-100 text-blue-800',
    'approved' => 'bg-green-100 text-green-800',
    'in_progress' => 'bg-yellow-100 text-yellow-800',
    'delivered' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-red-100 text-red-800'
];

$payment_classes = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'partial' => 'bg-blue-100 text-blue-800',
    'paid' => 'bg-green-100 text-green-800'
];
?>

<!-- Page content -->
<div class="container mx-auto px-4 py-4">
    
    <!-- Action buttons -->
    <div class="mb-6 flex flex-wrap gap-3">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
        
        <a href="create_order.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
            <i class="fas fa-plus mr-2"></i> Create New Order
        </a>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="flex flex-wrap md:flex-nowrap gap-4">
                <!-- Status filter -->
                <div class="w-full md:w-1/6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="submitted" <?= $filter_status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="delivered" <?= $filter_status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <!-- Payment status filter -->
                <div class="w-full md:w-1/6">
                    <label for="payment" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                    <select id="payment" name="payment" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="">All Payment Statuses</option>
                        <option value="pending" <?= $filter_payment === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="partial" <?= $filter_payment === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= $filter_payment === 'paid' ? 'selected' : '' ?>>Paid</option>
                    </select>
                </div>
                
                <!-- Supplier filter -->
                <div class="w-full md:w-1/6">
                    <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                    <select id="supplier" name="supplier" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="0">All Suppliers</option>
                        <?php if ($suppliers_result): ?>
                            <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                                <option value="<?= $supplier['supplier_id'] ?>" <?= $filter_supplier == $supplier['supplier_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Fuel Type filter -->
                <div class="w-full md:w-1/6">
                    <label for="fuel_type" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                    <select id="fuel_type" name="fuel_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="0">All Fuel Types</option>
                        <?php if ($fuel_types_result): ?>
                            <?php while ($fuel_type = $fuel_types_result->fetch_assoc()): ?>
                                <option value="<?= $fuel_type['fuel_type_id'] ?>" <?= $filter_fuel_type == $fuel_type['fuel_type_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Date range filter -->
                <div class="w-full md:w-1/6">
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <div class="w-full md:w-1/6">
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
            </div>
            
            <div class="flex gap-4 items-end">
                <!-- Search -->
                <div class="flex-grow">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search (PO Number / Supplier)</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Search..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Sort fields (hidden) -->
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_field) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($sort_direction) ?>">
                
                <!-- Submit and reset buttons -->
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="view_orders.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Orders table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Purchase Orders</h2>
                <div class="text-sm text-gray-600">
                    Showing <?= min(($page - 1) * $per_page + 1, $total_rows) ?> to <?= min($page * $per_page, $total_rows) ?> of <?= $total_rows ?> orders
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php 
                        // Function to generate sort URL
                        function getSortUrl($field) {
                            global $sort_field, $sort_direction;
                            
                            $params = $_GET;
                            $params['sort'] = $field;
                            $params['dir'] = ($sort_field == $field && $sort_direction == 'DESC') ? 'asc' : 'desc';
                            
                            return '?' . http_build_query($params);
                        }
                        
                        // Function to render sort indicator
                        function getSortIndicator($field) {
                            global $sort_field, $sort_direction;
                            
                            if ($sort_field != $field) {
                                return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
                            } elseif ($sort_direction == 'ASC') {
                                return '<i class="fas fa-sort-up text-blue-500 ml-1"></i>';
                            } else {
                                return '<i class="fas fa-sort-down text-blue-500 ml-1"></i>';
                            }
                        }
                        ?>
                        
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?= getSortUrl('po_number') ?>" class="flex items-center">
                                PO Number <?= getSortIndicator('po_number') ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?= getSortUrl('order_date') ?>" class="flex items-center">
                                Date <?= getSortIndicator('order_date') ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?= getSortUrl('supplier_name') ?>" class="flex items-center">
                                Supplier <?= getSortIndicator('supplier_name') ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Fuel Types
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?= getSortUrl('status') ?>" class="flex items-center">
                                Status <?= getSortIndicator('status') ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?= getSortUrl('total_amount') ?>" class="flex items-center justify-end">
                                Amount <?= getSortIndicator('total_amount') ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?= getSortUrl('payment_status') ?>" class="flex items-center">
                                Payment <?= getSortIndicator('payment_status') ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            // Get fuel types for this order
                            $fuel_types_query = "SELECT DISTINCT ft.fuel_name 
                                               FROM po_items poi 
                                               JOIN fuel_types ft ON poi.fuel_type_id = ft.fuel_type_id 
                                               WHERE poi.po_id = ?";
                            $fuel_stmt = $conn->prepare($fuel_types_query);
                            $fuel_stmt->bind_param("i", $row['po_id']);
                            $fuel_stmt->execute();
                            $fuel_result = $fuel_stmt->get_result();
                            
                            $fuel_types_in_order = [];
                            while ($fuel_row = $fuel_result->fetch_assoc()) {
                                $fuel_types_in_order[] = $fuel_row['fuel_name'];
                            }
                            $fuel_stmt->close();
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="order_details.php?id=<?= $row['po_id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                        <?= htmlspecialchars($row['po_number']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= date('M d, Y', strtotime($row['order_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= htmlspecialchars($row['supplier_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($fuel_types_in_order as $fuel_type): ?>
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                <?= htmlspecialchars($fuel_type) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $status_classes[$row['status']] ?? '' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                    <?= $currency_symbol ?><?= number_format($row['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $payment_classes[$row['payment_status']] ?? '' ?>">
                                        <?= ucfirst($row['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <div class="flex justify-center space-x-2">
                                        <a href="order_details.php?id=<?= $row['po_id'] ?>" class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (in_array($row['status'], ['draft', 'submitted']) && in_array($user_data['role'], ['admin', 'manager'])): ?>
                                        <a href="update_order.php?id=<?= $row['po_id'] ?>" class="text-yellow-600 hover:text-yellow-800" title="Edit Order">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['status'] === 'approved'): ?>
                                        <a href="order_details.php?id=<?= $row['po_id'] ?>&action=delivery" class="text-green-600 hover:text-green-800" title="Record Delivery">
                                            <i class="fas fa-truck"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['payment_status'] !== 'paid' && $row['status'] !== 'cancelled'): ?>
                                        <a href="process_payment.php?id=<?= $row['po_id'] ?>" class="text-blue-600 hover:text-blue-800" title="Process Payment">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                No orders found matching your search criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-between items-center bg-white rounded-lg shadow-md p-4">
        <div class="text-sm text-gray-700">
            Showing <?= min(($page - 1) * $per_page + 1, $total_rows) ?> to <?= min($page * $per_page, $total_rows) ?> of <?= $total_rows ?> entries
        </div>
        
        <div class="flex space-x-1">
            <?php
            // Function to generate pagination URL
            function getPaginationUrl($page_num) {
                $params = $_GET;
                $params['page'] = $page_num;
                return '?' . http_build_query($params);
            }
            
            // Previous button
            if ($page > 1): ?>
                <a href="<?= getPaginationUrl($page - 1) ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            
            <!-- Page numbers -->
            <?php
            $show_dots = false;
            for ($i = 1; $i <= $total_pages; $i++):
                // Logic to show only a subset of page numbers
                if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)):
                    $show_dots = true;
                ?>
                    <a href="<?= getPaginationUrl($i) ?>" class="px-3 py-1 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded-md">
                        <?= $i ?>
                    </a>
                <?php elseif ($show_dots): 
                    $show_dots = false;
                ?>
                    <span class="px-3 py-1 bg-gray-100 text-gray-400 rounded-md">...</span>
                <?php endif; 
            endfor; ?>
            
            <!-- Next button -->
            <?php if ($page < $total_pages): ?>
                <a href="<?= getPaginationUrl($page + 1) ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>