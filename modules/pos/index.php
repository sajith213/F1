<?php
/**
 * POS Module - Main Dashboard
 * 
 * This file serves as the main entry point for the Point of Sale module
 */

// Set page title and include header
$page_title = "Point of Sale";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <span class="text-gray-700">Point of Sale</span>';
include_once '../../includes/header.php';

// Require database connection
require_once '../../includes/db.php';

// Get quick stats for dashboard
$stats = [
    'total_products' => 0,
    'low_stock' => 0,
    'today_sales' => 0,
    'month_sales' => 0,
    'pending_orders' => 0,
    'awaiting_delivery' => 0  // New stat for purchase orders
];

// Count total products
$stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_products'] = $result->fetch_row()[0];
    $stmt->close();
}

// Count low stock products
$stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE current_stock <= reorder_level AND status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['low_stock'] = $result->fetch_row()[0];
    $stmt->close();
}

// Get today's sales amount
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sales WHERE DATE(sale_date) = ? AND payment_status != 'cancelled'");
if ($stmt) {
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_sales'] = $result->fetch_row()[0];
    $stmt->close();
}

// Get current month's sales amount
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $conn->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sales WHERE sale_date BETWEEN ? AND ? AND payment_status != 'cancelled'");
if ($stmt) {
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['month_sales'] = $result->fetch_row()[0];
    $stmt->close();
}

// NEW: Count pending purchase orders
$stmt = $conn->prepare("SELECT COUNT(*) FROM product_purchase_orders WHERE status IN ('ordered', 'partial')");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pending_orders'] = $result->fetch_row()[0];
    $stmt->close();
}

// NEW: Count awaiting delivery purchase orders
$stmt = $conn->prepare("SELECT COUNT(*) FROM product_purchase_orders WHERE status = 'ordered'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['awaiting_delivery'] = $result->fetch_row()[0];
    $stmt->close();
}

// Get recent sales (limit to 5)
$recent_sales = [];
$stmt = $conn->prepare("
    SELECT s.sale_id, s.invoice_number, s.sale_date, s.customer_name, s.sale_type, 
           s.total_amount, s.net_amount, s.payment_method, staff.first_name, staff.last_name
    FROM sales s
    LEFT JOIN staff ON s.staff_id = staff.staff_id
    WHERE s.payment_status != 'cancelled'
    ORDER BY s.sale_date DESC
    LIMIT 5
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_sales[] = $row;
    }
    $stmt->close();
}

// Get low stock products (limit to 5)
$low_stock_products = [];
$stmt = $conn->prepare("
    SELECT p.product_id, p.product_code, p.product_name, p.current_stock, p.reorder_level,
           c.category_name
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.category_id
    WHERE p.current_stock <= p.reorder_level AND p.status = 'active'
    ORDER BY (p.current_stock / p.reorder_level) ASC
    LIMIT 5
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $low_stock_products[] = $row;
    }
    $stmt->close();
}

// NEW: Get recent purchase orders
$recent_orders = [];
$stmt = $conn->prepare("
    SELECT po.po_id, po.po_number, po.order_date, po.status, po.total_amount,
           s.supplier_name, u.full_name as created_by
    FROM product_purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN users u ON po.created_by = u.user_id
    ORDER BY po.order_date DESC
    LIMIT 5
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
    $stmt->close();
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

<!-- Main content container -->
<div class="container mx-auto pb-6">
    
    <!-- Quick actions buttons -->
    <div class="flex flex-wrap gap-4 mb-6">
        <a href="sales.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-cash-register mr-2 text-lg"></i>
            New Sale
        </a>
        <a href="add_product.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-plus-circle mr-2 text-lg"></i>
            Add Product
        </a>
        <a href="view_products.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-boxes mr-2 text-lg"></i>
            View Products
        </a>
        <!-- NEW: Purchase Order button -->
        <a href="../purchase/index.php" <a href="#" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg flex items-center">
    <i class="fas fa-shopping-basket mr-2 text-lg"></i>
    Purchase
</a>
        </a>
        <a href="../reports/sales_reports.php" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-chart-bar mr-2 text-lg"></i>
            Sales Reports
        </a>
    </div>
    
    <!-- Stats cards - IMPROVED WITH CONSISTENT SIZING -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <!-- Total Products -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full flex flex-col">
        <div class="p-4 bg-blue-500 flex-grow">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <p class="text-sm font-medium uppercase tracking-wider">PRODUCTS</p>
                    <h2 class="text-3xl font-bold mt-1">1</h2>
                </div>
                <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                    <i class="fas fa-boxes text-2xl text-white"></i>
                </div>
            </div>
        </div>
        <div class="px-4 py-2 bg-gray-50">
            <a href="view_products.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View all products</a>
        </div>
    </div>
    
    <!-- Low Stock -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full flex flex-col">
        <div class="p-4 bg-gray-700 flex-grow">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <p class="text-sm font-medium uppercase tracking-wider">LOW STOCK</p>
                    <h2 class="text-3xl font-bold mt-1">0</h2>
                </div>
                <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                    <i class="fas fa-exclamation-triangle text-2xl text-white"></i>
                </div>
            </div>
        </div>
        <div class="px-4 py-2 bg-gray-50">
            <a href="view_products.php?filter=low_stock" class="text-gray-600 hover:text-gray-800 text-sm font-medium">View low stock</a>
        </div>
    </div>
    
    <!-- Today's Sales -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full flex flex-col">
        <div class="p-4 bg-green-500 flex-grow">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <p class="text-sm font-medium uppercase tracking-wider">TODAY'S SALES</p>
                    <h2 class="text-3xl font-bold mt-1">Rs. 14,160.00</h2>
                </div>
                <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                    <i class="fas fa-hand-holding-usd text-2xl text-white"></i>
                </div>
            </div>
        </div>
        <div class="px-4 py-2 bg-gray-50">
            <a href="../reports/sales_reports.php?period=today" class="text-green-600 hover:text-green-800 text-sm font-medium">View details</a>
        </div>
    </div>
    
    <!-- Monthly Sales -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full flex flex-col">
        <div class="p-4 bg-purple-500 flex-grow">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <p class="text-sm font-medium uppercase tracking-wider">MONTH TO DATE</p>
                    <h2 class="text-3xl font-bold mt-1">Rs. 33,630.00</h2>
                </div>
                <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                    <i class="fas fa-calendar-alt text-2xl text-white"></i>
                </div>
            </div>
        </div>
        <div class="px-4 py-2 bg-gray-50">
            <a href="../reports/sales_reports.php?period=month" class="text-purple-600 hover:text-purple-800 text-sm font-medium">View monthly report</a>
        </div>
    </div>
    
    <!-- Pending Orders -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full flex flex-col">
        <div class="p-4 bg-red-700 flex-grow">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <p class="text-sm font-medium uppercase tracking-wider">PENDING ORDERS</p>
                    <h2 class="text-3xl font-bold mt-1">2</h2>
                </div>
                <div class="bg-red-500 bg-opacity-30 rounded-full p-3">
                    <i class="fas fa-clipboard-list text-2xl text-white"></i>
                </div>
            </div>
        </div>
        <div class="px-4 py-2 bg-gray-50">
            <a href="../purchase/order_list.php?status=pending" class="text-red-600 hover:text-red-800 text-sm font-medium">View orders</a>
        </div>
    </div>
    
    <!-- Deliveries -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full flex flex-col">
        <div class="p-4 bg-blue-600 flex-grow">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <p class="text-sm font-medium uppercase tracking-wider">DELIVERIES</p>
                    <h2 class="text-3xl font-bold mt-1">2</h2>
                </div>
                <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                    <i class="fas fa-truck text-2xl text-white"></i>
                </div>
            </div>
        </div>
        <div class="px-4 py-2 bg-gray-50">
            <a href="../purchase/receive_products.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Receive products</a>
        </div>
    </div>
</div>
    <!-- Two columns layout for tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Recent Sales -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="flex justify-between items-center border-b px-6 py-4">
                <h3 class="text-xl font-semibold text-gray-800">Recent Sales</h3>
                <a href="../reports/sales_reports.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($recent_sales)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-sm text-center text-gray-500">No recent sales found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="receipt.php?id=<?= $sale['sale_id'] ?>" class="text-blue-600 hover:text-blue-900 font-medium">
                                    <?= htmlspecialchars($sale['invoice_number']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, g:i A', strtotime($sale['sale_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?= $currency_symbol ?> <?= number_format($sale['net_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $badge_class = "";
                                switch ($sale['sale_type']) {
                                    case 'fuel':
                                        $badge_class = "bg-blue-100 text-blue-800";
                                        break;
                                    case 'product':
                                        $badge_class = "bg-green-100 text-green-800";
                                        break;
                                    case 'mixed':
                                        $badge_class = "bg-purple-100 text-purple-800";
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $badge_class ?>">
                                    <?= ucfirst($sale['sale_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Low Stock Products -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="flex justify-between items-center border-b px-6 py-4">
                <h3 class="text-xl font-semibold text-gray-800">Low Stock Products</h3>
                <a href="view_products.php?filter=low_stock" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($low_stock_products)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-sm text-center text-gray-500">No low stock products found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($low_stock_products as $product): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?= htmlspecialchars($product['product_code']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="update_product.php?id=<?= $product['product_id'] ?>" class="text-blue-600 hover:text-blue-900 font-medium">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="<?= $product['current_stock'] == 0 ? 'text-red-600 font-bold' : 'text-orange-600' ?>">
                                    <?= $product['current_stock'] ?> / <?= $product['reorder_level'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $stock_status = "Reorder";
                                $badge_class = "bg-yellow-100 text-yellow-800";
                                if ($product['current_stock'] == 0) {
                                    $stock_status = "Out of Stock";
                                    $badge_class = "bg-red-100 text-red-800";
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $badge_class ?>">
                                    <?= $stock_status ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- NEW: Recent Purchase Orders Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h3 class="text-xl font-semibold text-gray-800">Recent Purchase Orders</h3>
            <a href="../purchase/order_list.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($recent_orders)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-sm text-center text-gray-500">No recent purchase orders found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="../purchase/view_order.php?id=<?= $order['po_id'] ?>" class="text-blue-600 hover:text-blue-900 font-medium">
                                <?= htmlspecialchars($order['po_number']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?= htmlspecialchars($order['supplier_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('M d, Y', strtotime($order['order_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?= $currency_symbol ?> <?= number_format($order['total_amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status_class = "";
                            switch ($order['status']) {
                                case 'draft':
                                    $status_class = "bg-gray-100 text-gray-800";
                                    break;
                                case 'ordered':
                                    $status_class = "bg-blue-100 text-blue-800";
                                    break;
                                case 'partial':
                                    $status_class = "bg-yellow-100 text-yellow-800";
                                    break;
                                case 'delivered':
                                    $status_class = "bg-green-100 text-green-800";
                                    break;
                                case 'cancelled':
                                    $status_class = "bg-red-100 text-red-800";
                                    break;
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($order['created_by']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="../purchase/view_order.php?id=<?= $order['po_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if ($order['status'] === 'ordered' || $order['status'] === 'partial'): ?>
                            <a href="../purchase/receive_products.php?po_id=<?= $order['po_id'] ?>" class="text-green-600 hover:text-green-900">
                                <i class="fas fa-truck-loading"></i> Receive
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
    
    <!-- Quick Action Card for Purchase Orders -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Purchase Order Quick Actions</h3>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="../purchase/create_order.php" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                <div class="p-3 bg-blue-500 rounded-full mb-3 text-white">
                    <i class="fas fa-plus text-xl"></i>
                </div>
                <h4 class="font-medium text-blue-800">Create Purchase Order</h4>
                <p class="text-sm text-blue-600 text-center mt-1">Order new products from suppliers</p>
            </a>
            
            <a href="../purchase/receive_products.php" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                <div class="p-3 bg-green-500 rounded-full mb-3 text-white">
                    <i class="fas fa-truck-loading text-xl"></i>
                </div>
                <h4 class="font-medium text-green-800">Receive Products</h4>
                <p class="text-sm text-green-600 text-center mt-1">Process incoming deliveries</p>
            </a>
            
            <a href="../purchase/suppliers.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                <div class="p-3 bg-purple-500 rounded-full mb-3 text-white">
                    <i class="fas fa-building text-xl"></i>
                </div>
                <h4 class="font-medium text-purple-800">Manage Suppliers</h4>
                <p class="text-sm text-purple-600 text-center mt-1">Add or edit supplier information</p>
            </a>
        </div>
    </div>
    
</div>

<?php include_once '../../includes/footer.php'; ?>