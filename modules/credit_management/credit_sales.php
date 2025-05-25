<?php
/**
 * Credit Management - Credit Sales List
 * 
 * This file displays a list of credit sales with search, filter, and pagination
 */

// Set page title and include header
$page_title = "Credit Sales";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Credit Management</a> / <span class="text-gray-700">Credit Sales</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables for filtering and pagination
$search = $_GET['search'] ?? '';
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Query to get credit sales
$sales_query = "
    SELECT cs.*, s.invoice_number, s.sale_date, s.net_amount, 
           c.customer_name, c.phone_number
    FROM credit_sales cs
    JOIN sales s ON cs.sale_id = s.sale_id
    JOIN credit_customers c ON cs.customer_id = c.customer_id
    WHERE 1=1
";

$count_query = "
    SELECT COUNT(*) as total
    FROM credit_sales cs
    JOIN sales s ON cs.sale_id = s.sale_id
    JOIN credit_customers c ON cs.customer_id = c.customer_id
    WHERE 1=1
";

$params = [];
$types = "";

// Apply filters
if (!empty($search)) {
    $search_term = "%$search%";
    $sales_query .= " AND (c.customer_name LIKE ? OR s.invoice_number LIKE ?)";
    $count_query .= " AND (c.customer_name LIKE ? OR s.invoice_number LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if ($customer_id > 0) {
    $sales_query .= " AND cs.customer_id = ?";
    $count_query .= " AND cs.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

if (!empty($status)) {
    $sales_query .= " AND cs.status = ?";
    $count_query .= " AND cs.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($start_date) && !empty($end_date)) {
    $sales_query .= " AND DATE(s.sale_date) BETWEEN ? AND ?";
    $count_query .= " AND DATE(s.sale_date) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// Add ordering
$sales_query .= " ORDER BY s.sale_date DESC";

// Add pagination
$sales_query .= " LIMIT ?, ?";
$query_params = $params;
$query_params[] = $offset;
$query_params[] = $per_page;
$query_types = $types . "ii";

// Execute count query
$total_records = 0;
$total_pages = 1;
$sales = [];

if (isset($conn) && $conn) {
    // Get total count
    $stmt = $conn->prepare($count_query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $total_records = $row['total'];
            $total_pages = ceil($total_records / $per_page);
        }
        $stmt->close();
    }
    
    // Get sales with pagination
    $stmt = $conn->prepare($sales_query);
    if ($stmt) {
        if (!empty($query_params)) {
            $stmt->bind_param($query_types, ...$query_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
        $stmt->close();
    }
    
    // Get list of customers for filter dropdown
    $customers = [];
    $customer_query = "SELECT customer_id, customer_name FROM credit_customers ORDER BY customer_name";
    $customer_result = $conn->query($customer_query);
    if ($customer_result) {
        while ($row = $customer_result->fetch_assoc()) {
            $customers[] = $row;
        }
    }
}

// Get currency symbol
$currency_symbol = '$';
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
        <h2 class="text-2xl font-bold text-gray-800">Credit Sales</h2>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Customer name or invoice..." 
                       class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                <select id="customer_id" name="customer_id" 
                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['customer_id'] ?>" <?= $customer_id == $c['customer_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['customer_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" 
                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" 
                       class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" 
                       class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="credit_sales.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg ml-2">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Sales table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                Credit Sales
                <?php if ($total_records > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($total_records) ?> records)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($sales)): ?>
        <div class="p-6 text-center text-gray-500">
            No credit sales found. Try adjusting your search criteria.
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                            <a href="../pos/receipt.php?id=<?= $sale['sale_id'] ?>"><?= htmlspecialchars($sale['invoice_number']) ?></a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($sale['customer_name']) ?></div>
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($sale['phone_number']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('M d, Y', strtotime($sale['sale_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('M d, Y', strtotime($sale['due_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            <?= $currency_symbol ?> <?= number_format($sale['credit_amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                            <span class="<?= $sale['remaining_amount'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $currency_symbol ?> <?= number_format($sale['remaining_amount'], 2) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <?php 
                            $status_class = 'bg-yellow-100 text-yellow-800';
                            if ($sale['status'] === 'partial') {
                                $status_class = 'bg-blue-100 text-blue-800';
                            } elseif ($sale['status'] === 'paid') {
                                $status_class = 'bg-green-100 text-green-800';
                            } elseif ($sale['status'] === 'overdue') {
                                $status_class = 'bg-red-100 text-red-800';
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                <?= ucfirst($sale['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <?php if ($sale['remaining_amount'] > 0): ?>
                            <a href="../credit_settlement/add_settlement.php?customer_id=<?= $sale['customer_id'] ?>&invoice_id=<?= $sale['sale_id'] ?>" class="text-green-600 hover:text-green-900">
                                <i class="fas fa-money-bill-wave mr-1"></i> Make Payment
                            </a>
                            <?php else: ?>
                            <a href="../pos/receipt.php?id=<?= $sale['sale_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-receipt mr-1"></i> View
                            </a>
                            <?php endif; ?>
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
                Showing <?= ($offset + 1) ?>-<?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> records
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="?page=<?= ($page - 1) ?>&search=<?= urlencode($search) ?>&customer_id=<?= $customer_id ?>&status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    Previous
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= ($page + 1) ?>&search=<?= urlencode($search) ?>&customer_id=<?= $customer_id ?>&status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    Next
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>