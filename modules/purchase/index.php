<?php
/**
 * Purchase Orders Dashboard
 */
$page_title = "Purchase Orders";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <span class="text-gray-700">Purchase Orders</span>';
include_once '../../includes/header.php';
require_once '../../includes/db.php';

// Get stats for dashboard
$stats = [
    'pending_orders' => 0,
    'this_month_orders' => 0,
    'this_month_amount' => 0,
    'awaiting_delivery' => 0
];

// Count pending orders
$stmt = $conn->prepare("SELECT COUNT(*) FROM product_purchase_orders WHERE status = 'ordered'");
if ($stmt) {
    $stmt->execute();
    $stats['pending_orders'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
}

// Get current month orders and amount
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM product_purchase_orders WHERE order_date BETWEEN ? AND ?");
if ($stmt) {
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_row();
    $stats['this_month_orders'] = $result[0];
    $stats['this_month_amount'] = $result[1];
    $stmt->close();
}

// Count awaiting delivery
$stmt = $conn->prepare("SELECT COUNT(*) FROM product_purchase_orders WHERE status IN ('ordered', 'partial')");
if ($stmt) {
    $stmt->execute();
    $stats['awaiting_delivery'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
}

// Get recent purchase orders
$recent_orders = [];
$stmt = $conn->prepare("
    SELECT po.*, s.supplier_name, u.full_name as created_by_name
    FROM product_purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN users u ON po.created_by = u.user_id
    ORDER BY po.order_date DESC
    LIMIT 10
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
    $stmt->close();
}

// Get currency symbol
$currency_symbol = 'Rs.';
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $currency_symbol = $row['setting_value'];
}
?>

<!-- Main content -->
<div class="container mx-auto pb-6">
    
    <!-- Quick action buttons -->
    <div class="flex flex-wrap gap-4 mb-6">
        <a href="create_order.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-plus-circle mr-2 text-lg"></i>
            New Purchase Order
        </a>
        <a href="suppliers.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-building mr-2 text-lg"></i>
            Manage Suppliers
        </a>
        <a href="receive_products.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-truck-loading mr-2 text-lg"></i>
            Receive Products
        </a>
        <a href="../reports/purchase_reports.php" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-3 px-6 rounded-lg flex items-center">
            <i class="fas fa-chart-bar mr-2 text-lg"></i>
            Purchase Reports
        </a>
    </div>
    
    <!-- Stats cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Pending Orders -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-blue-500 to-blue-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Pending Orders</p>
                        <h2 class="text-3xl font-bold mt-1"><?= number_format($stats['pending_orders']) ?></h2>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-clipboard-list text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            <div class="px-4 py-2 bg-gray-50">
                <a href="order_list.php?status=ordered" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View pending orders</a>
            </div>
        </div>
        
        <!-- Monthly Orders -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-green-500 to-green-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">This Month</p>
                        <h2 class="text-3xl font-bold mt-1"><?= number_format($stats['this_month_orders']) ?> Orders</h2>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-calendar-check text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            <div class="px-4 py-2 bg-gray-50">
                <a href="order_list.php?period=month" class="text-green-600 hover:text-green-800 text-sm font-medium">View monthly orders</a>
            </div>
        </div>
        
        <!-- Monthly Amount -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-purple-500 to-purple-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Month Spending</p>
                        <h2 class="text-3xl font-bold mt-1"><?= $currency_symbol ?> <?= number_format($stats['this_month_amount'], 2) ?></h2>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-money-bill-wave text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            <div class="px-4 py-2 bg-gray-50">
                <a href="../reports/purchase_reports.php?period=month" class="text-purple-600 hover:text-purple-800 text-sm font-medium">View financial reports</a>
            </div>
        </div>
        
        <!-- Awaiting Delivery -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-orange-500 to-orange-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Awaiting Delivery</p>
                        <h2 class="text-3xl font-bold mt-1"><?= number_format($stats['awaiting_delivery']) ?></h2>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-truck text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            <div class="px-4 py-2 bg-gray-50">
                <a href="receive_products.php" class="text-orange-600 hover:text-orange-800 text-sm font-medium">Receive products</a>
            </div>
        </div>
    </div>
    
    <!-- Recent purchase orders -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="flex justify-between items-center border-b px-6 py-4">
            <h3 class="text-xl font-semibold text-gray-800">Recent Purchase Orders</h3>
            <a href="order_list.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($recent_orders)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-sm text-center text-gray-500">No purchase orders found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="view_order.php?id=<?= $order['po_id'] ?>" class="text-blue-600 hover:text-blue-900 font-medium">
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
                                <a href="view_order.php?id=<?= $order['po_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($order['status'] === 'draft'): ?>
                                <a href="edit_order.php?id=<?= $order['po_id'] ?>" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-edit"></i> Edit
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

<?php include_once '../../includes/footer.php'; ?>