<?php
/**
 * Fuel Order & Payment Report
 * 
 * A simplified report to track all incoming fuel orders and their payment status.
 */

// Set page title
$page_title = "Fuel Order & Payment Report";

// Include necessary files
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user has permission
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../includes/footer.php';
    exit;
}

// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-90 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$supplier_id = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : null;
$fuel_type_id = isset($_GET['fuel_type_id']) ? $_GET['fuel_type_id'] : null;
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
    $fuel_type_id = !empty($_POST['fuel_type_id']) ? $_POST['fuel_type_id'] : null;
    $payment_status = !empty($_POST['payment_status']) ? $_POST['payment_status'] : null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'start_date' => $start_date,
        'end_date' => $end_date,
        'supplier_id' => $supplier_id,
        'fuel_type_id' => $fuel_type_id,
        'payment_status' => $payment_status
    ]);
    
    header("Location: fuel_order_report.php?$query");
    exit;
}

// Fetch suppliers for dropdown
function getSuppliers($conn) {
    $sql = "SELECT 
                supplier_id,
                supplier_name,
                contact_person
            FROM 
                suppliers
            WHERE 
                status = 'active'
            ORDER BY 
                supplier_name";
    
    $result = $conn->query($sql);
    
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    
    return $suppliers;
}

// Fetch fuel types for dropdown
function getFuelTypes($conn) {
    $sql = "SELECT 
                fuel_type_id,
                fuel_name
            FROM 
                fuel_types
            ORDER BY 
                fuel_name";
    
    $result = $conn->query($sql);
    
    $fuelTypes = [];
    while ($row = $result->fetch_assoc()) {
        $fuelTypes[] = $row;
    }
    
    return $fuelTypes;
}

// Get order data with details
function getOrderData($conn, $start_date, $end_date, $supplier_id = null, $fuel_type_id = null, $payment_status = null) {
    $sql = "SELECT 
                po.po_id,
                po.po_number,
                po.order_date,
                po.expected_delivery_date,
                po.status as order_status,
                po.payment_status,
                po.total_amount,
                s.supplier_id,
                s.supplier_name,
                s.contact_person,
                fd.delivery_id,
                fd.delivery_date,
                fd.status as delivery_status,
                COUNT(DISTINCT poi.fuel_type_id) as fuel_types_count,
                SUM(poi.quantity) as total_ordered_quantity,
                GROUP_CONCAT(DISTINCT ft.fuel_name) as fuel_types,
                (SELECT SUM(di.quantity_received) 
                 FROM fuel_deliveries fd2 
                 JOIN delivery_items di ON fd2.delivery_id = di.delivery_id 
                 WHERE fd2.po_id = po.po_id) as total_received_quantity,
                (SELECT payment_date FROM payment_history ph WHERE ph.po_id = po.po_id ORDER BY payment_date DESC LIMIT 1) as last_payment_date
            FROM 
                purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.supplier_id
                JOIN po_items poi ON po.po_id = poi.po_id
                JOIN fuel_types ft ON poi.fuel_type_id = ft.fuel_type_id
                LEFT JOIN fuel_deliveries fd ON po.po_id = fd.po_id AND fd.status != 'cancelled'
            WHERE 
                po.order_date BETWEEN ? AND ?";
    
    $params = [$start_date, $end_date];
    $types = "ss";
    
    if ($supplier_id) {
        $sql .= " AND s.supplier_id = ?";
        $params[] = $supplier_id;
        $types .= "i";
    }
    
    if ($fuel_type_id) {
        $sql .= " AND EXISTS (SELECT 1 FROM po_items poi2 WHERE poi2.po_id = po.po_id AND poi2.fuel_type_id = ?)";
        $params[] = $fuel_type_id;
        $types .= "i";
    }
    
    if ($payment_status) {
        $sql .= " AND po.payment_status = ?";
        $params[] = $payment_status;
        $types .= "s";
    }
    
    $sql .= " GROUP BY po.po_id
              ORDER BY po.order_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate delivery status percentage
        if ($row['total_ordered_quantity'] > 0 && $row['total_received_quantity'] > 0) {
            $row['delivery_percentage'] = min(100, ($row['total_received_quantity'] / $row['total_ordered_quantity']) * 100);
        } else {
            $row['delivery_percentage'] = 0;
        }
        
        $data[] = $row;
    }
    
    return $data;
}

// Get order details for a specific PO
function getOrderDetails($conn, $po_id) {
    // Get PO items
    $sql = "SELECT 
                poi.po_item_id,
                poi.fuel_type_id,
                ft.fuel_name,
                poi.quantity,
                poi.unit_price,
                poi.line_total,
                (SELECT SUM(di.quantity_received) 
                 FROM delivery_items di 
                 JOIN fuel_deliveries fd ON di.delivery_id = fd.delivery_id 
                 WHERE fd.po_id = poi.po_id AND di.fuel_type_id = poi.fuel_type_id) as received_quantity
            FROM 
                po_items poi
                JOIN fuel_types ft ON poi.fuel_type_id = ft.fuel_type_id
            WHERE 
                poi.po_id = ?
            ORDER BY 
                ft.fuel_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    
    // Get delivery history
    $sql = "SELECT 
                fd.delivery_id,
                fd.delivery_date,
                fd.delivery_reference,
                fd.status as delivery_status,
                u.full_name as received_by,
                fd.notes
            FROM 
                fuel_deliveries fd
                JOIN users u ON fd.received_by = u.user_id
            WHERE 
                fd.po_id = ?
            ORDER BY 
                fd.delivery_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $deliveries_result = $stmt->get_result();
    
    $deliveries = [];
    while ($row = $deliveries_result->fetch_assoc()) {
        $deliveries[] = $row;
    }
    
    // Get payment history
    $sql = "SELECT 
                ph.payment_id,
                ph.payment_date,
                ph.amount,
                ph.payment_method,
                ph.reference_number,
                u.full_name as recorded_by,
                ph.notes
            FROM 
                payment_history ph
                JOIN users u ON ph.recorded_by = u.user_id
            WHERE 
                ph.po_id = ?
            ORDER BY 
                ph.payment_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $payments_result = $stmt->get_result();
    
    $payments = [];
    while ($row = $payments_result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    return [
        'items' => $items,
        'deliveries' => $deliveries,
        'payments' => $payments
    ];
}

// Calculate summary statistics
function calculateSummaryStats($order_data) {
    $summary = [
        'total_orders' => count($order_data),
        'total_order_value' => 0,
        'total_received_quantity' => 0,
        'total_ordered_quantity' => 0,
        'status_counts' => [
            'pending' => 0,
            'partial' => 0,
            'delivered' => 0,
            'cancelled' => 0
        ],
        'payment_counts' => [
            'paid' => 0,
            'partial' => 0,
            'pending' => 0
        ]
    ];
    
    foreach ($order_data as $order) {
        $summary['total_order_value'] += $order['total_amount'];
        $summary['total_ordered_quantity'] += $order['total_ordered_quantity'];
        $summary['total_received_quantity'] += $order['total_received_quantity'] ?: 0;
        
        // Count by delivery status
        if (isset($order['delivery_status'])) {
            $status = $order['delivery_status'];
            if (isset($summary['status_counts'][$status])) {
                $summary['status_counts'][$status]++;
            }
        } else {
            // If no delivery yet, count as pending
            $summary['status_counts']['pending']++;
        }
        
        // Count by payment status
        $payment_status = $order['payment_status'];
        if (isset($summary['payment_counts'][$payment_status])) {
            $summary['payment_counts'][$payment_status]++;
        }
    }
    
    // Calculate delivery fulfillment percentage
    if ($summary['total_ordered_quantity'] > 0) {
        $summary['fulfillment_percentage'] = ($summary['total_received_quantity'] / $summary['total_ordered_quantity']) * 100;
    } else {
        $summary['fulfillment_percentage'] = 0;
    }
    
    return $summary;
}

// Get data for the report
$suppliers = getSuppliers($conn);
$fuel_types = getFuelTypes($conn);
$order_data = getOrderData($conn, $start_date, $end_date, $supplier_id, $fuel_type_id, $payment_status);
$summary_stats = calculateSummaryStats($order_data);

// Check if the request is for order details via AJAX
if (isset($_GET['get_order_details']) && isset($_GET['po_id'])) {
    $po_id = intval($_GET['po_id']);
    $details = getOrderDetails($conn, $po_id);
    header('Content-Type: application/json');
    echo json_encode($details);
    exit;
}

?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Fuel Order & Payment Report</h2>
    
    <!-- Filter Form -->
    <form method="POST" action="" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <!-- Date Range -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <!-- Supplier Filter -->
            <div>
                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= $supplier['supplier_id'] ?>" <?= $supplier_id == $supplier['supplier_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($supplier['supplier_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Fuel Type Filter -->
            <div>
                <label for="fuel_type_id" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                <select id="fuel_type_id" name="fuel_type_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Fuel Types</option>
                    <?php foreach ($fuel_types as $fuel): ?>
                    <option value="<?= $fuel['fuel_type_id'] ?>" <?= $fuel_type_id == $fuel['fuel_type_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fuel['fuel_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Payment Status Filter -->
            <div>
                <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <select id="payment_status" name="payment_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
        </div>
        
        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
    </form>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Orders -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="text-sm font-medium text-blue-800 mb-1">Total Orders</div>
            <div class="text-2xl font-bold text-blue-900"><?= number_format($summary_stats['total_orders']) ?></div>
            <div class="text-xs text-blue-700 mt-1">In selected period</div>
        </div>
        
        <!-- Total Order Value -->
        <div class="bg-green-50 rounded-lg p-4 border border-green-100">
            <div class="text-sm font-medium text-green-800 mb-1">Total Order Value</div>
            <div class="text-2xl font-bold text-green-900"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_order_value'], 2) ?></div>
        </div>
        
        <!-- Delivery Status -->
        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100">
            <div class="text-sm font-medium text-indigo-800 mb-1">Delivery Fulfillment</div>
            <div class="text-2xl font-bold text-indigo-900"><?= number_format($summary_stats['fulfillment_percentage'], 1) ?>%</div>
            <div class="text-xs text-indigo-700 mt-1">
                <?= number_format($summary_stats['total_received_quantity']) ?> / <?= number_format($summary_stats['total_ordered_quantity']) ?> liters
            </div>
        </div>
        
        <!-- Payment Status -->
        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-100">
            <div class="text-sm font-medium text-yellow-800 mb-1">Payment Status</div>
            <div class="flex items-center space-x-2 mt-2">
                <div class="flex-1">
                    <div class="text-xs text-gray-600">Paid</div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1 mb-1">
                        <div class="bg-green-500 h-1.5 rounded-full" style="width: <?= ($summary_stats['total_orders'] > 0) ? ($summary_stats['payment_counts']['paid'] / $summary_stats['total_orders'] * 100) : 0 ?>%"></div>
                    </div>
                    <div class="text-xs font-medium"><?= $summary_stats['payment_counts']['paid'] ?></div>
                </div>
                <div class="flex-1">
                    <div class="text-xs text-gray-600">Partial</div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1 mb-1">
                        <div class="bg-yellow-500 h-1.5 rounded-full" style="width: <?= ($summary_stats['total_orders'] > 0) ? ($summary_stats['payment_counts']['partial'] / $summary_stats['total_orders'] * 100) : 0 ?>%"></div>
                    </div>
                    <div class="text-xs font-medium"><?= $summary_stats['payment_counts']['partial'] ?></div>
                </div>
                <div class="flex-1">
                    <div class="text-xs text-gray-600">Pending</div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1 mb-1">
                        <div class="bg-red-500 h-1.5 rounded-full" style="width: <?= ($summary_stats['total_orders'] > 0) ? ($summary_stats['payment_counts']['pending'] / $summary_stats['total_orders'] * 100) : 0 ?>%"></div>
                    </div>
                    <div class="text-xs font-medium"><?= $summary_stats['payment_counts']['pending'] ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Orders Table -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Purchase Orders</h3>
    
    <?php if (empty($order_data)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800">
        <p class="font-medium">No orders found for the selected criteria.</p>
        <p class="text-sm mt-1">Try adjusting your filters or date range.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">PO Number</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Order Date</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Supplier</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Types</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Order Value</th>
                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Delivery Status</th>
                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Payment Status</th>
                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($order_data as $order): ?>
                    <tr class="hover:bg-gray-50 order-row" data-po-id="<?= $order['po_id'] ?>">
                        <td class="py-3 px-4 text-sm font-medium text-blue-600 hover:text-blue-800 cursor-pointer toggle-details">
                            <?= htmlspecialchars($order['po_number']) ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800">
                            <?= date('M d, Y', strtotime($order['order_date'])) ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800">
                            <div><?= htmlspecialchars($order['supplier_name']) ?></div>
                            <?php if ($order['contact_person']): ?>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($order['contact_person']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($order['fuel_types']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right font-medium">
                            <?= CURRENCY_SYMBOL . ' ' . number_format($order['total_amount'], 2) ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if ($order['delivery_percentage'] === 0): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                Pending
                            </span>
                            <?php elseif ($order['delivery_percentage'] < 100): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Partial (<?= number_format($order['delivery_percentage']) ?>%)
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Complete
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if ($order['payment_status'] === 'paid'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Paid
                            </span>
                            <?php elseif ($order['payment_status'] === 'partial'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Partial
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Pending
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <button class="toggle-details text-blue-600 hover:text-blue-800" data-po-id="<?= $order['po_id'] ?>">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </td>
                    </tr>
                    <!-- Details row (hidden by default) -->
                    <tr class="bg-gray-50 details-row hidden" id="details-<?= $order['po_id'] ?>">
                        <td colspan="8" class="py-3 px-4">
                            <div class="p-2 details-content">
                                <i class="fas fa-spinner fa-spin text-blue-500"></i> Loading details...
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="flex justify-end mt-4 space-x-2">
        <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-print mr-2"></i> Print
        </button>
        <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
            <i class="fas fa-file-pdf mr-2"></i> PDF
        </button>
        <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-file-excel mr-2"></i> Excel
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle order details
        const toggleButtons = document.querySelectorAll('.toggle-details');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const poId = this.dataset.poId || this.closest('tr').dataset.poId;
                const detailsRow = document.getElementById(`details-${poId}`);
                
                // Toggle visibility
                if (detailsRow.classList.contains('hidden')) {
                    // Show details row
                    detailsRow.classList.remove('hidden');
                    
                    // Change icon to up arrow
                    const iconElement = this.querySelector('i') || document.querySelector(`button[data-po-id="${poId}"] i`);
                    if (iconElement) {
                        iconElement.classList.remove('fa-chevron-down');
                        iconElement.classList.add('fa-chevron-up');
                    }
                    
                    // Load details if not already loaded
                    const detailsContent = detailsRow.querySelector('.details-content');
                    if (detailsContent.innerHTML.includes('Loading details')) {
                        loadOrderDetails(poId, detailsContent);
                    }
                } else {
                    // Hide details row
                    detailsRow.classList.add('hidden');
                    
                    // Change icon to down arrow
                    const iconElement = this.querySelector('i') || document.querySelector(`button[data-po-id="${poId}"] i`);
                    if (iconElement) {
                        iconElement.classList.remove('fa-chevron-up');
                        iconElement.classList.add('fa-chevron-down');
                    }
                }
            });
        });
        
        // Function to load order details via AJAX
        function loadOrderDetails(poId, container) {
            fetch(`fuel_order_report.php?get_order_details=1&po_id=${poId}`)
                .then(response => response.json())
                .then(data => {
                    // Render details content
                    container.innerHTML = renderOrderDetails(data);
                })
                .catch(error => {
                    console.error('Error loading order details:', error);
                    container.innerHTML = '<div class="text-red-500">Error loading details. Please try again.</div>';
                });
        }
        
        // Function to render order details HTML
        function renderOrderDetails(data) {
            let html = '<div class="space-y-4">';
            
            // Order items section
            html += '<div>';
            html += '<h4 class="text-sm font-medium text-gray-800 mb-2">Order Items</h4>';
            html += '<div class="overflow-x-auto">';
            html += '<table class="min-w-full border border-gray-200">';
            html += '<thead class="bg-gray-50"><tr>';
            html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>';
            html += '<th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Ordered Qty (L)</th>';
            html += '<th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Received Qty (L)</th>';
            html += '<th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Unit Price</th>';
            html += '<th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Total</th>';
            html += '</tr></thead>';
            html += '<tbody class="divide-y divide-gray-200">';
            
            data.items.forEach(item => {
                const receivedQty = item.received_quantity || 0;
                const fulfillmentPercent = item.quantity > 0 ? (receivedQty / item.quantity) * 100 : 0;
                
                html += '<tr class="hover:bg-gray-50">';
                html += `<td class="py-2 px-3 text-sm text-gray-800">${item.fuel_name}</td>`;
                html += `<td class="py-2 px-3 text-sm text-gray-800 text-right">${Number(item.quantity).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>`;
                html += `<td class="py-2 px-3 text-sm text-gray-800 text-right">
                            ${Number(receivedQty).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                <div class="${fulfillmentPercent >= 100 ? 'bg-green-500' : (fulfillmentPercent > 0 ? 'bg-yellow-500' : 'bg-gray-300')} h-1.5 rounded-full" 
                                     style="width: ${Math.min(100, fulfillmentPercent)}%"></div>
                            </div>
                         </td>`;
                html += `<td class="py-2 px-3 text-sm text-gray-800 text-right">${CURRENCY_SYMBOL} ${Number(item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>`;
                html += `<td class="py-2 px-3 text-sm text-gray-800 text-right font-medium">${CURRENCY_SYMBOL} ${Number(item.line_total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody></table></div></div>';
            
            // Tabs for Deliveries and Payments
            html += '<div class="border-t border-gray-200 pt-4 mt-4">';
            html += '<div class="flex border-b border-gray-200">';
            html += '<button class="px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600 tab-btn active" data-tab="deliveries">Deliveries</button>';
            html += '<button class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent hover:border-gray-300 tab-btn" data-tab="payments">Payments</button>';
            html += '</div>';
            
            // Deliveries Tab Content
            html += '<div class="py-4 tab-content active" id="deliveries-tab">';
            if (data.deliveries.length === 0) {
                html += '<p class="text-sm text-gray-500">No deliveries recorded yet.</p>';
            } else {
                html += '<div class="overflow-x-auto">';
                html += '<table class="min-w-full border border-gray-200">';
                html += '<thead class="bg-gray-50"><tr>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Delivery Date</th>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Reference</th>';
                html += '<th class="py-2 px-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Status</th>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Received By</th>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Notes</th>';
                html += '</tr></thead>';
                html += '<tbody class="divide-y divide-gray-200">';
                
                data.deliveries.forEach(delivery => {
                    html += '<tr class="hover:bg-gray-50">';
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${new Date(delivery.delivery_date).toLocaleDateString()}</td>`;
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${delivery.delivery_reference || '-'}</td>`;
                    
                    // Status badge
                    let statusClass = 'bg-gray-100 text-gray-800';
                    if (delivery.delivery_status === 'complete') {
                        statusClass = 'bg-green-100 text-green-800';
                    } else if (delivery.delivery_status === 'partial') {
                        statusClass = 'bg-yellow-100 text-yellow-800';
                    }
                    
                    html += `<td class="py-2 px-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                    ${delivery.delivery_status.charAt(0).toUpperCase() + delivery.delivery_status.slice(1)}
                                </span>
                             </td>`;
                    
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${delivery.received_by}</td>`;
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${delivery.notes || '-'}</td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
            }
            html += '</div>';
            
            // Payments Tab Content
            html += '<div class="py-4 tab-content hidden" id="payments-tab">';
            if (data.payments.length === 0) {
                html += '<p class="text-sm text-gray-500">No payments recorded yet.</p>';
            } else {
                html += '<div class="overflow-x-auto">';
                html += '<table class="min-w-full border border-gray-200">';
                html += '<thead class="bg-gray-50"><tr>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Payment Date</th>';
                html += '<th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Amount</th>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Method</th>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Reference</th>';
                html += '<th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Recorded By</th>';
                html += '</tr></thead>';
                html += '<tbody class="divide-y divide-gray-200">';
                
                data.payments.forEach(payment => {
                    html += '<tr class="hover:bg-gray-50">';
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${new Date(payment.payment_date).toLocaleDateString()}</td>`;
                    html += `<td class="py-2 px-3 text-sm text-green-600 font-medium text-right">${CURRENCY_SYMBOL} ${Number(payment.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>`;
                    
                    // Format payment method
                    const methodMap = {
                        'cash': 'Cash',
                        'bank_transfer': 'Bank Transfer',
                        'check': 'Check',
                        'credit_card': 'Credit Card',
                        'other': 'Other'
                    };
                    
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${methodMap[payment.payment_method] || payment.payment_method}</td>`;
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${payment.reference_number || '-'}</td>`;
                    html += `<td class="py-2 px-3 text-sm text-gray-800">${payment.recorded_by}</td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
            }
            html += '</div>';
            
            html += '</div>'; // End of tabs container
            html += '</div>'; // End of details container
            
            // Add tab switching functionality
            setTimeout(() => {
                const tabBtns = document.querySelectorAll('.tab-btn');
                tabBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        // Remove active class from all buttons and contents
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'text-blue-600', 'border-blue-600'));
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.add('text-gray-500', 'border-transparent'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                        
                        // Add active class to clicked button and corresponding content
                        this.classList.add('active', 'text-blue-600', 'border-blue-600');
                        this.classList.remove('text-gray-500', 'border-transparent');
                        const tabId = this.dataset.tab;
                        const tabContent = document.getElementById(`${tabId}-tab`);
                        if (tabContent) {
                            tabContent.classList.remove('hidden');
                            tabContent.classList.add('active');
                        }
                    });
                });
            }, 100);
            
            return html;
        }
    });
    
    // Currency symbol for formatting
    const CURRENCY_SYMBOL = '<?= CURRENCY_SYMBOL ?>';
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF (placeholder)
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library
    }
    
    // Export to Excel (placeholder)
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require a spreadsheet library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>