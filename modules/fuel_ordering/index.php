<?php
/**
 * Fuel Ordering Module - Main Page
 * 
 * This page serves as the main dashboard for the fuel ordering module,
 * displaying order summaries, recent orders, and providing quick actions.
 */

// Set page title
$page_title = "Fuel Ordering";

// Set breadcrumbs
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:text-blue-800">Home</a> / Fuel Ordering';

// Include header
include_once '../../includes/header.php';

// Include module functions
require_once 'functions.php';

// Check for permissions (placeholder for proper permission system)
if (!in_array($user_data['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get summary data for dashboard cards
require_once '../../includes/db.php';

// Get currency symbol
$currency_symbol = get_currency_symbol();

// Recent orders
$recent_orders_query = "
    SELECT po.po_id, po.po_number, po.order_date, po.status, po.total_amount, po.payment_status,
           s.supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    ORDER BY po.order_date DESC
    LIMIT 5";
$recent_orders_result = $conn->query($recent_orders_query);

// Pending deliveries
$pending_deliveries_query = "
    SELECT fd.delivery_id, fd.delivery_date, fd.status, po.po_number, po.po_id
    FROM fuel_deliveries fd
    JOIN purchase_orders po ON fd.po_id = po.po_id
    WHERE fd.status IN ('pending', 'partial')
    ORDER BY fd.delivery_date
    LIMIT 5";
$pending_deliveries_result = $conn->query($pending_deliveries_query);

// Summary counts
$summary_stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'pending_deliveries' => 0,
    'total_amount' => 0
];

$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status IN ('draft', 'submitted', 'approved', 'in_progress') THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
    (SELECT COUNT(*) FROM fuel_deliveries WHERE status IN ('pending', 'partial')) as pending_deliveries,
    SUM(total_amount) as total_amount
    FROM purchase_orders";
$stats_result = $conn->query($stats_query);

if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
    $summary_stats = array_merge($summary_stats, $stats_row);
}

// Get low stock fuel types for reordering recommendations
$low_stock_query = "
    SELECT ft.fuel_type_id, ft.fuel_name, t.tank_id, t.tank_name, t.current_volume, t.capacity,
           (t.current_volume / t.capacity * 100) as percentage
    FROM tanks t
    JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
    WHERE (t.current_volume / t.capacity * 100) < 25
    ORDER BY percentage ASC";
$low_stock_result = $conn->query($low_stock_query);
?>

<!-- Main content -->
<div class="container mx-auto px-4">
    
    <!-- Quick action buttons -->
    <div class="flex flex-wrap gap-4 mb-6">
        <a href="create_order.php" class="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg shadow-md transition-colors duration-300">
            <i class="fas fa-plus mr-2"></i> Create New Order
        </a>
        <a href="view_orders.php" class="flex items-center justify-center bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg shadow-md transition-colors duration-300">
            <i class="fas fa-list mr-2"></i> View All Orders
        </a>
        <a href="suppliers.php" class="flex items-center justify-center bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg shadow-md transition-colors duration-300">
            <i class="fas fa-building mr-2"></i> Manage Suppliers
        </a>
    </div>
    
    <!-- Summary cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Orders -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-blue-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm uppercase font-medium">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($summary_stats['total_orders'] ?? 0) ?></p>
                </div>
                <div class="rounded-full bg-blue-100 p-3 text-blue-500">
                    <i class="fas fa-file-invoice text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending Orders -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-yellow-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm uppercase font-medium">Pending Orders</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($summary_stats['pending_orders'] ?? 0) ?></p>
                </div>
                <div class="rounded-full bg-yellow-100 p-3 text-yellow-500">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending Deliveries -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-green-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm uppercase font-medium">Pending Deliveries</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($summary_stats['pending_deliveries'] ?? 0) ?></p>
                </div>
                <div class="rounded-full bg-green-100 p-3 text-green-500">
                    <i class="fas fa-truck text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Total Amount -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-purple-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm uppercase font-medium">Total Amount</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?><?= number_format($summary_stats['total_amount'] ?? 0, 2) ?></p>
                </div>
                <div class="rounded-full bg-purple-100 p-3 text-purple-500">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Low stock alerts -->
    <?php if ($low_stock_result && $low_stock_result->num_rows > 0): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <h2 class="text-lg font-bold text-red-800 mb-2">
            <i class="fas fa-exclamation-triangle mr-2"></i> Low Fuel Stock Alert
        </h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tank</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Volume</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php while ($row = $low_stock_result->fetch_assoc()): ?>
                    <tr>
                        <td class="py-2 px-3 text-sm"><?= htmlspecialchars($row['fuel_name']) ?></td>
                        <td class="py-2 px-3 text-sm"><?= htmlspecialchars($row['tank_name']) ?></td>
                        <td class="py-2 px-3 text-sm"><?= number_format($row['current_volume'], 2) ?> L</td>
                        <td class="py-2 px-3 text-sm"><?= number_format($row['capacity'], 2) ?> L</td>
                        <td class="py-2 px-3 text-sm">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-red-600 h-2.5 rounded-full" style="width: <?= min(100, max(5, $row['percentage'])) ?>%"></div>
                            </div>
                            <span class="text-xs"><?= number_format($row['percentage'], 1) ?>%</span>
                        </td>
                        <td class="py-2 px-3 text-sm">
                            <a href="create_order.php?fuel_type=<?= $row['fuel_type_id'] ?>" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-plus-circle mr-1"></i> Order Now
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent orders -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-lg font-semibold text-gray-800">Recent Orders</h2>
            </div>
            <div class="overflow-x-auto p-4">
                <?php if ($recent_orders_result && $recent_orders_result->num_rows > 0): ?>
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($row = $recent_orders_result->fetch_assoc()): ?>
                        <tr>
                            <td class="py-2 px-3 text-sm">
                                <a href="order_details.php?id=<?= $row['po_id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                    <?= htmlspecialchars($row['po_number']) ?>
                                </a>
                            </td>
                            <td class="py-2 px-3 text-sm"><?= date('M d, Y', strtotime($row['order_date'])) ?></td>
                            <td class="py-2 px-3 text-sm">
                                <?php
                                $status_classes = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'submitted' => 'bg-blue-100 text-blue-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'in_progress' => 'bg-yellow-100 text-yellow-800',
                                    'delivered' => 'bg-emerald-100 text-emerald-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $status_class = $status_classes[$row['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full <?= $status_class ?>">
                                    <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                </span>
                            </td>
                            <td class="py-2 px-3 text-sm"><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td class="py-2 px-3 text-sm font-medium"><?= $currency_symbol ?><?= number_format($row['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-4 text-gray-500">
                    <p>No recent orders found.</p>
                </div>
                <?php endif; ?>
                <div class="mt-3 text-right">
                    <a href="view_orders.php" class="text-sm text-blue-600 hover:text-blue-800">View all orders <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>
        
        <!-- Pending deliveries -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-lg font-semibold text-gray-800">Pending Deliveries</h2>
            </div>
            <div class="overflow-x-auto p-4">
                <?php if ($pending_deliveries_result && $pending_deliveries_result->num_rows > 0): ?>
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Date</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($row = $pending_deliveries_result->fetch_assoc()): ?>
                        <tr>
                            <td class="py-2 px-3 text-sm"><?= $row['delivery_id'] ?></td>
                            <td class="py-2 px-3 text-sm">
                                <a href="order_details.php?id=<?= $row['po_id'] ?>" class="text-blue-600 hover:text-blue-800">
                                    <?= htmlspecialchars($row['po_number']) ?>
                                </a>
                            </td>
                            <td class="py-2 px-3 text-sm"><?= date('M d, Y', strtotime($row['delivery_date'])) ?></td>
                            <td class="py-2 px-3 text-sm">
                                <?php
                                $delivery_status_classes = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'partial' => 'bg-blue-100 text-blue-800',
                                    'complete' => 'bg-green-100 text-green-800'
                                ];
                                $delivery_status_class = $delivery_status_classes[$row['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full <?= $delivery_status_class ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="py-2 px-3 text-sm">
                                <a href="order_details.php?id=<?= $row['po_id'] ?>&action=delivery" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-truck-loading mr-1"></i> Receive
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-4 text-gray-500">
                    <p>No pending deliveries found.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>