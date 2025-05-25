<?php
/**
 * View Purchase Order Details
 */
$page_title = "Purchase Order Details";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Purchase Orders</a> / <span class="text-gray-700">View Order</span>';
include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check permission
if (!has_permission('view_purchase_orders') && !has_permission('create_purchase_order')) {
    header("Location: ../../index.php");
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">Invalid order ID</div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get purchase order details
$order = null;
$stmt = $conn->prepare("
    SELECT po.*, s.supplier_name, s.contact_person, s.phone, s.email, u.full_name as created_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN users u ON po.created_by = u.user_id
    WHERE po.po_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
}

if (!$order) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">Purchase order not found</div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get order items
$items = [];
$stmt = $conn->prepare("
    SELECT pi.*, ft.fuel_name
    FROM po_items pi
    LEFT JOIN fuel_types ft ON pi.fuel_type_id = ft.fuel_type_id
    WHERE pi.po_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

// Get delivery history
$deliveries = [];
$stmt = $conn->prepare("
    SELECT fd.*, u.full_name as received_by_name
    FROM fuel_deliveries fd
    LEFT JOIN users u ON fd.received_by = u.user_id
    WHERE fd.po_id = ?
    ORDER BY fd.delivery_date DESC
");

if ($stmt) {
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }
    $stmt->close();
}

// Get payment history
$payments = [];
$stmt = $conn->prepare("
    SELECT ph.*, u.full_name as recorded_by_name
    FROM payment_history ph
    LEFT JOIN users u ON ph.recorded_by = u.user_id
    WHERE ph.po_id = ?
    ORDER BY ph.payment_date DESC
");

if ($stmt) {
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
}

// Format currency for display
function format_amount($amount) {
    global $conn;
    $currency_symbol = 'Rs.';
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $currency_symbol = $row['setting_value'];
    }
    return $currency_symbol . ' ' . number_format($amount, 2);
}

// Get status badge class
function get_status_class($status) {
    switch ($status) {
        case 'draft':
            return "bg-gray-100 text-gray-800";
        case 'submitted':
            return "bg-blue-100 text-blue-800";
        case 'approved':
            return "bg-green-100 text-green-800";
        case 'in_progress':
            return "bg-yellow-100 text-yellow-800";
        case 'delivered':
            return "bg-green-100 text-green-800";
        case 'cancelled':
            return "bg-red-100 text-red-800";
        default:
            return "bg-gray-100 text-gray-800";
    }
}
?>

<!-- Main content -->
<div class="container mx-auto pb-6">
    <!-- Action buttons -->
    <div class="flex flex-wrap gap-3 mb-6">
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
        
        <a href="print_order.php?id=<?= $order_id ?>" target="_blank" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded flex items-center">
            <i class="fas fa-print mr-2"></i> Print
        </a>
        
        <?php if ($order['status'] == 'draft' || $order['status'] == 'submitted'): ?>
        <a href="edit_order.php?id=<?= $order_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded flex items-center">
            <i class="fas fa-edit mr-2"></i> Edit
        </a>
        <?php endif; ?>
        
        <?php if ($order['status'] == 'approved' || $order['status'] == 'in_progress'): ?>
        <a href="receive_delivery.php?po_id=<?= $order_id ?>" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded flex items-center">
            <i class="fas fa-truck-loading mr-2"></i> Record Delivery
        </a>
        <?php endif; ?>
        
        <?php if ($order['status'] != 'cancelled' && $order['status'] != 'delivered'): ?>
        <a href="cancel_order.php?id=<?= $order_id ?>" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded flex items-center" onclick="return confirm('Are you sure you want to cancel this order?');">
            <i class="fas fa-ban mr-2"></i> Cancel
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Order header -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Purchase Order: <?= htmlspecialchars($order['po_number']) ?></h2>
                <p class="text-sm text-gray-600">Created on <?= date('F j, Y', strtotime($order['created_at'])) ?> by <?= htmlspecialchars($order['created_by_name']) ?></p>
            </div>
            <span class="px-4 py-2 rounded-full text-sm font-semibold <?= get_status_class($order['status']) ?>">
                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
            </span>
        </div>
        
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Order details -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-3">Order Details</h3>
                <table class="min-w-full">
                    <tr>
                        <td class="py-2 text-gray-600">Order Date:</td>
                        <td class="py-2 font-medium"><?= date('F j, Y', strtotime($order['order_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Expected Delivery:</td>
                        <td class="py-2 font-medium">
                            <?= $order['expected_delivery_date'] ? date('F j, Y', strtotime($order['expected_delivery_date'])) : 'Not specified' ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Total Amount:</td>
                        <td class="py-2 font-medium"><?= format_amount($order['total_amount']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Payment Status:</td>
                        <td class="py-2">
                            <?php
                            $payment_status_class = '';
                            switch ($order['payment_status']) {
                                case 'pending':
                                    $payment_status_class = 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'partial':
                                    $payment_status_class = 'bg-blue-100 text-blue-800';
                                    break;
                                case 'paid':
                                    $payment_status_class = 'bg-green-100 text-green-800';
                                    break;
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $payment_status_class ?>">
                                <?= ucfirst($order['payment_status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php if (!empty($order['notes'])): ?>
                    <tr>
                        <td class="py-2 text-gray-600">Notes:</td>
                        <td class="py-2"><?= nl2br(htmlspecialchars($order['notes'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Supplier details -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-3">Supplier Information</h3>
                <table class="min-w-full">
                    <tr>
                        <td class="py-2 text-gray-600">Name:</td>
                        <td class="py-2 font-medium"><?= htmlspecialchars($order['supplier_name']) ?></td>
                    </tr>
                    <?php if ($order['contact_person']): ?>
                    <tr>
                        <td class="py-2 text-gray-600">Contact Person:</td>
                        <td class="py-2"><?= htmlspecialchars($order['contact_person']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="py-2 text-gray-600">Phone:</td>
                        <td class="py-2"><?= htmlspecialchars($order['phone']) ?></td>
                    </tr>
                    <?php if ($order['email']): ?>
                    <tr>
                        <td class="py-2 text-gray-600">Email:</td>
                        <td class="py-2"><?= htmlspecialchars($order['email']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Order items -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Order Items</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (L)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Line Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-sm text-center text-gray-500">No items found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($item['fuel_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= number_format($item['quantity'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= format_amount($item['unit_price']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php
                                $line_total = isset($item['line_total']) ? $item['line_total'] : ($item['quantity'] * $item['unit_price']);
                                echo format_amount($line_total);
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Total row -->
                        <tr class="bg-gray-50">
                            <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-900 text-right">Total:</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                <?= format_amount($order['total_amount']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Delivery history -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Delivery History</h3>
        </div>
        <?php if (empty($deliveries)): ?>
        <div class="p-6 text-center text-gray-500">
            No deliveries recorded yet
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('F j, Y', strtotime($delivery['delivery_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($delivery['delivery_reference']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $delivery_status_class = '';
                            switch ($delivery['status']) {
                                case 'pending':
                                    $delivery_status_class = 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'partial':
                                    $delivery_status_class = 'bg-blue-100 text-blue-800';
                                    break;
                                case 'complete':
                                    $delivery_status_class = 'bg-green-100 text-green-800';
                                    break;
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $delivery_status_class ?>">
                                <?= ucfirst($delivery['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($delivery['received_by_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <a href="view_delivery.php?id=<?= $delivery['delivery_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment history -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Payment History</h3>
        </div>
        <?php if (empty($payments)): ?>
        <div class="p-6 text-center text-gray-500">
            No payments recorded yet
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('F j, Y', strtotime($payment['payment_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= format_amount($payment['amount']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($payment['reference_number'] ?: 'N/A') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($payment['recorded_by_name']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Total row -->
                    <tr class="bg-gray-50">
                        <td colspan="1" class="px-6 py-4 text-sm font-medium text-gray-900 text-right">Total Paid:</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                            <?php
                            $total_paid = 0;
                            foreach ($payments as $payment) {
                                $total_paid += $payment['amount'];
                            }
                            echo format_amount($total_paid);
                            ?>
                        </td>
                        <td colspan="3" class="px-6 py-4 text-sm text-gray-500">
                            <?php if ($total_paid < $order['total_amount']): ?>
                            <span class="font-medium text-red-600">Outstanding: <?= format_amount($order['total_amount'] - $total_paid) ?></span>
                            <?php elseif ($total_paid > $order['total_amount']): ?>
                            <span class="font-medium text-green-600">Overpaid: <?= format_amount($total_paid - $order['total_amount']) ?></span>
                            <?php else: ?>
                            <span class="font-medium text-green-600">Fully Paid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($order['payment_status'] != 'paid'): ?>
        <div class="p-6 border-t border-gray-200">
            <a href="record_payment.php?po_id=<?= $order_id ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-money-bill-wave mr-2"></i> Record Payment
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>