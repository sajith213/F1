<?php
/**
 * Credit Management - Credit Settlements List
 * 
 * This file displays a list of credit settlements with search, filter, and pagination
 */

// Set page title and include header
$page_title = "Credit Settlements";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="../credit_management/credit_customers.php">Credit Customers</a> / <span class="text-gray-700">Settlements</span>';

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
$payment_method = $_GET['payment_method'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Base query for settlements
$baseQuery = "
    SELECT s.*, 
           c.customer_name, c.phone_number,
           u.full_name as recorded_by_name
    FROM credit_settlements s
    JOIN credit_customers c ON s.customer_id = c.customer_id
    LEFT JOIN users u ON s.recorded_by = u.user_id
    WHERE 1=1
";

$countQuery = "SELECT COUNT(*) FROM credit_settlements s WHERE 1=1";
$params = [];
$types = "";

// Apply customer filter
if ($customer_id > 0) {
    $baseQuery .= " AND s.customer_id = ?";
    $countQuery .= " AND s.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

// Apply payment method filter
if (!empty($payment_method)) {
    $baseQuery .= " AND s.payment_method = ?";
    $countQuery .= " AND s.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

// Apply date range filter
if (!empty($start_date) && !empty($end_date)) {
    $baseQuery .= " AND DATE(s.settlement_date) BETWEEN ? AND ?";
    $countQuery .= " AND DATE(s.settlement_date) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// Apply search filter
if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $baseQuery .= " AND (c.customer_name LIKE ? OR s.reference_no LIKE ? OR s.notes LIKE ?)";
    $countQuery .= " AND (c.customer_name LIKE ? OR s.reference_no LIKE ? OR s.notes LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Add sorting
$baseQuery .= " ORDER BY s.settlement_date DESC";

// Add pagination
$baseQuery .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Initialize variables for results
$total_records = 0;
$total_pages = 1;
$settlements = [];

// Get customer list for filter dropdown
$customers = [];
$customerQuery = "SELECT customer_id, customer_name FROM credit_customers WHERE status = 'active' ORDER BY customer_name";
$customerResult = $conn->query($customerQuery);
if ($customerResult) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Execute database queries
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

    // Execute main query with pagination
    $stmt = $conn->prepare($baseQuery);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $settlements[] = $row;
        }
        $stmt->close();
    }
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_settlements,
            SUM(amount) as total_amount,
            COUNT(DISTINCT customer_id) as unique_customers
        FROM credit_settlements
        WHERE DATE(settlement_date) BETWEEN ? AND ?
    ";
    
    $summaryStmt = $conn->prepare($summaryQuery);
    if ($summaryStmt) {
        $summaryStmt->bind_param("ss", $start_date, $end_date);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        $summary = $summaryResult ? $summaryResult->fetch_assoc() : null;
        $summaryStmt->close();
    }
}

// Check if success message exists
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// Check if error message exists
$error_message = $_SESSION['error_message'] ?? '';
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}

// Get currency symbol from settings
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
        <h2 class="text-2xl font-bold text-gray-800">Credit Settlements</h2>
        
        <div class="flex gap-2">
            <a href="add_settlement.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>
                Add New Settlement
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
    
    <!-- Error Message -->
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <?php if ($summary): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Total Settlements -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Settlements</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format((int)$summary['total_settlements']) ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-file-invoice text-blue-500"></i>
                </div>
            </div>
        </div>
        
        <!-- Total Amount -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Amount</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format((float)$summary['total_amount'], 2) ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-green-500"></i>
                </div>
            </div>
        </div>
        
        <!-- Unique Customers -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Unique Customers</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format((int)$summary['unique_customers']) ?></p>
                </div>
                <div class="bg-purple-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-users text-purple-500"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters and search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Search box -->
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                        placeholder="Search by customer, reference..." 
                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <!-- Customer filter -->
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <select id="customer_id" name="customer_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['customer_id'] ?>" <?= $customer_id == $customer['customer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['customer_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Payment method filter -->
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="">All Methods</option>
                        <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bank_transfer" <?= $payment_method === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="check" <?= $payment_method === 'check' ? 'selected' : '' ?>>Check</option>
                        <option value="mobile_payment" <?= $payment_method === 'mobile_payment' ? 'selected' : '' ?>>Mobile Payment</option>
                    </select>
                </div>
                
                <!-- Date range filter -->
                <div class="md:col-span-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" 
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" 
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
            </div>
            
            <!-- Filter buttons -->
            <div class="flex justify-end space-x-3">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="credit_settlements.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Settlements table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-xl font-semibold text-gray-800">
                Settlement List
                <?php if ($total_records > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format((int)$total_records) ?> settlements)</span>
                <?php endif; ?>
            </h3>
            <a href="#" onclick="window.print()" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-print mr-1"></i> Print
            </a>
        </div>
        
        <?php if (empty($settlements)): ?>
            <div class="p-6 text-center text-gray-500">
                No settlements found for the selected filters. Adjust your criteria or 
                <a href="add_settlement.php" class="text-blue-600 hover:text-blue-800">add a new settlement</a>.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($settlements as $settlement): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= date('M d, Y', strtotime($settlement['settlement_date'])) ?>
                                <div class="text-xs text-gray-500">
                                    <?= date('h:i A', strtotime($settlement['settlement_date'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="../credit_management/view_credit_customer.php?id=<?= $settlement['customer_id'] ?>" class="hover:text-blue-600">
                                        <?= htmlspecialchars($settlement['customer_name']) ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= htmlspecialchars($settlement['phone_number']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($settlement['reference_no'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $methodBadge = '';
                                $methodIcon = '';
                                
                                switch ($settlement['payment_method']) {
                                    case 'cash':
                                        $methodBadge = 'bg-green-100 text-green-800';
                                        $methodIcon = 'fa-money-bill-wave';
                                        break;
                                    case 'bank_transfer':
                                        $methodBadge = 'bg-blue-100 text-blue-800';
                                        $methodIcon = 'fa-university';
                                        break;
                                    case 'check':
                                        $methodBadge = 'bg-purple-100 text-purple-800';
                                        $methodIcon = 'fa-money-check';
                                        break;
                                    case 'mobile_payment':
                                        $methodBadge = 'bg-indigo-100 text-indigo-800';
                                        $methodIcon = 'fa-mobile-alt';
                                        break;
                                    default:
                                        $methodBadge = 'bg-gray-100 text-gray-800';
                                        $methodIcon = 'fa-credit-card';
                                }
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $methodBadge ?>">
                                    <i class="fas <?= $methodIcon ?> mr-1"></i>
                                    <?= ucwords(str_replace('_', ' ', $settlement['payment_method'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                <?= $currency_symbol ?> <?= number_format((float)$settlement['amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($settlement['recorded_by_name'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right space-x-2">
                                <a href="settlement_receipt.php?id=<?= $settlement['settlement_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-receipt"></i> Receipt
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
                    Showing <?= number_format((int)($offset + 1)) ?> to <?= number_format((int)min($offset + $per_page, $total_records)) ?> of <?= number_format((int)$total_records) ?> settlements
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&customer_id=<?= $customer_id ?>&payment_method=<?= $payment_method ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&customer_id=<?= $customer_id ?>&payment_method=<?= $payment_method ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
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

<!-- Print specific styles -->
<style media="print">
    .bg-white { background-color: white !important; }
    .shadow-md { box-shadow: none !important; }
    .container { max-width: 100% !important; }
    header, footer, form, button, a, .no-print { display: none !important; }
    th, td { padding: 8px !important; }
    body { font-size: 12px !important; }
    h2, h3 { font-size: 16px !important; margin-bottom: 10px !important; }
    @page { margin: 1cm; }
</style>

<?php include_once '../../includes/footer.php'; ?>