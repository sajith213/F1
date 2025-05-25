<?php
/**
 * Fuel Reports
 * 
 * This file generates various reports related to fuel inventory, consumption, and sales.
 */
ob_start();
// Set page title
$page_title = "Fuel Reports";

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

// Get report type from URL parameter
$report_type = isset($_GET['type']) ? $_GET['type'] : 'consumption';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'consumption';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    
    // Redirect to maintain bookmarkable URLs
    header("Location: fuel_reports.php?type=$report_type&start_date=$start_date&end_date=$end_date");
    exit;
}

// Function to get fuel consumption data
function getFuelConsumptionData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                ft.fuel_name,
                SUM(mr.volume_dispensed) as total_volume
            FROM 
                meter_readings mr
                JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
            WHERE 
                mr.reading_date BETWEEN ? AND ?
            GROUP BY 
                ft.fuel_type_id
            ORDER BY 
                total_volume DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get fuel inventory data
function getFuelInventoryData($conn) {
    $sql = "SELECT 
                t.tank_id,
                t.tank_name,
                ft.fuel_name,
                t.capacity,
                t.current_volume,
                (t.current_volume / t.capacity) * 100 as percentage_full,
                CASE 
                    WHEN t.current_volume <= t.low_level_threshold THEN 'low'
                    WHEN t.current_volume < (t.capacity * 0.5) THEN 'medium'
                    ELSE 'good'
                END as status
            FROM 
                tanks t
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            ORDER BY 
                percentage_full ASC";
    
    $result = $conn->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get fuel delivery history
function getFuelDeliveryData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                fd.delivery_id,
                fd.delivery_date,
                fd.po_id,
                s.supplier_name,
                ft.fuel_name,
                di.quantity_received,
                po.status,
                po.payment_status
            FROM 
                fuel_deliveries fd
                JOIN delivery_items di ON fd.delivery_id = di.delivery_id
                JOIN fuel_types ft ON di.fuel_type_id = ft.fuel_type_id
                JOIN purchase_orders po ON fd.po_id = po.po_id
                JOIN suppliers s ON po.supplier_id = s.supplier_id
            WHERE 
                fd.delivery_date BETWEEN ? AND ?
            ORDER BY 
                fd.delivery_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get fuel sales data
function getFuelSalesData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                ft.fuel_name,
                SUM(si.quantity) as total_quantity,
                SUM(si.net_amount) as total_sales,
                AVG(si.unit_price) as average_price
            FROM 
                sale_items si
                JOIN sales s ON si.sale_id = s.sale_id
                JOIN pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
            WHERE 
                si.item_type = 'fuel' AND
                DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY 
                ft.fuel_type_id
            ORDER BY 
                total_sales DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get price history data
function getPriceHistoryData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                fp.effective_date,
                ft.fuel_name,
                fp.purchase_price,
                fp.selling_price,
                fp.profit_margin,
                fp.profit_percentage,
                u.full_name as set_by
            FROM 
                fuel_prices fp
                JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
                JOIN users u ON fp.set_by = u.user_id
            WHERE 
                fp.effective_date BETWEEN ? AND ?
            ORDER BY 
                fp.effective_date DESC, ft.fuel_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'inventory':
        $report_data = getFuelInventoryData($conn);
        $report_title = "Current Fuel Inventory";
        break;
        
    case 'delivery':
        $report_data = getFuelDeliveryData($conn, $start_date, $end_date);
        $report_title = "Fuel Delivery History";
        break;
        
    case 'sales':
        $report_data = getFuelSalesData($conn, $start_date, $end_date);
        $report_title = "Fuel Sales Analysis";
        break;
        
    case 'price':
        $report_data = getPriceHistoryData($conn, $start_date, $end_date);
        $report_title = "Fuel Price History";
        break;
        
    case 'consumption':
    default:
        $report_data = getFuelConsumptionData($conn, $start_date, $end_date);
        $report_title = "Fuel Consumption Report";
        break;
}

?>

<!-- Filter form -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="POST" action="" class="w-full">
        <div class="flex flex-col md:flex-row md:items-end md:space-x-4">
            <div class="mb-4 md:mb-0 flex-1">
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="consumption" <?= $report_type === 'consumption' ? 'selected' : '' ?>>Fuel Consumption</option>
                    <option value="inventory" <?= $report_type === 'inventory' ? 'selected' : '' ?>>Current Inventory</option>
                    <option value="delivery" <?= $report_type === 'delivery' ? 'selected' : '' ?>>Delivery History</option>
                    <option value="sales" <?= $report_type === 'sales' ? 'selected' : '' ?>>Sales Analysis</option>
                    <option value="price" <?= $report_type === 'price' ? 'selected' : '' ?>>Price History</option>
                </select>
            </div>
            
            <div class="mb-4 md:mb-0">
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" <?= $report_type === 'inventory' ? 'disabled' : '' ?>>
            </div>
            
            <div class="mb-4 md:mb-0">
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" <?= $report_type === 'inventory' ? 'disabled' : '' ?>>
            </div>
            
            <div>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Report header with export buttons -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0"><?= htmlspecialchars($report_title) ?></h2>
        
        <div class="flex space-x-2">
            <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-print mr-2"></i> Print
            </button>
            <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-pdf mr-2"></i> PDF
            </button>
            <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-excel mr-2"></i> Excel
            </button>
        </div>
    </div>
</div>

<!-- Report content - changes based on report type -->
<div class="bg-white rounded-lg shadow-md overflow-hidden" id="report-content">
    <?php if (empty($report_data)): ?>
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
            <p>No data found for the selected criteria.</p>
        </div>
    <?php else: ?>
        <?php if ($report_type === 'consumption'): ?>
            <!-- Fuel Consumption Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Volume (Liters)</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $total_volume = array_sum(array_column($report_data, 'total_volume'));
                        foreach ($report_data as $row): 
                            $percentage = ($total_volume > 0) ? ($row['total_volume'] / $total_volume) * 100 : 0;
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['fuel_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['total_volume'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <div class="text-xs text-center mt-1"><?= number_format($percentage, 2) ?>%</div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($total_volume, 2) ?></td>
                            <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
        <?php elseif ($report_type === 'inventory'): ?>
            <!-- Current Inventory Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tank</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current (L)</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Fill Level</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tank_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['fuel_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['capacity'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['current_volume'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="<?= $row['status'] === 'low' ? 'bg-red-600' : ($row['status'] === 'medium' ? 'bg-yellow-500' : 'bg-green-600') ?> h-2.5 rounded-full" style="width: <?= $row['percentage_full'] ?>%"></div>
                                    </div>
                                    <div class="text-xs text-center mt-1"><?= number_format($row['percentage_full'], 2) ?>%</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($row['status'] === 'low'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Low
                                        </span>
                                    <?php elseif ($row['status'] === 'medium'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Medium
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Good
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_type === 'delivery'): ?>
            <!-- Fuel Delivery History Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO #</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (L)</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($row['delivery_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['po_id']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['supplier_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['fuel_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['quantity_received'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($row['status'] === 'delivered'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Delivered
                                        </span>
                                    <?php elseif ($row['status'] === 'partial'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Partial
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($row['payment_status'] === 'paid'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Paid
                                        </span>
                                    <?php elseif ($row['payment_status'] === 'partial'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Partial
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_type === 'sales'): ?>
            <!-- Fuel Sales Analysis Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Quantity (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Average Price</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $total_sales = array_sum(array_column($report_data, 'total_sales'));
                        foreach ($report_data as $row): 
                            $percentage = ($total_sales > 0) ? ($row['total_sales'] / $total_sales) * 100 : 0;
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['fuel_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['total_quantity'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= CURRENCY_SYMBOL . number_format($row['total_sales'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= CURRENCY_SYMBOL . number_format($row['average_price'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <div class="text-xs text-center mt-1"><?= number_format($percentage, 2) ?>%</div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'total_quantity')), 2) ?></td>
                            <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($total_sales, 2) ?></td>
                            <td class="px-6 py-3"></td>
                            <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
        <?php elseif ($report_type === 'price'): ?>
            <!-- Fuel Price History Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Price</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Selling Price</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit Margin</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit %</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Set By</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($row['effective_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['fuel_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= CURRENCY_SYMBOL . number_format($row['purchase_price'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= CURRENCY_SYMBOL . number_format($row['selling_price'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= CURRENCY_SYMBOL . number_format($row['profit_margin'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['profit_percentage'], 2) ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['set_by']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- JavaScript for export functionality -->
<script>
    // Toggle date inputs based on report type
    document.getElementById('report_type').addEventListener('change', function() {
        const dateInputs = document.querySelectorAll('#start_date, #end_date');
        if (this.value === 'inventory') {
            dateInputs.forEach(input => {
                input.disabled = true;
            });
        } else {
            dateInputs.forEach(input => {
                input.disabled = false;
            });
        }
    });
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library like TCPDF or FPDF
    }
    
    // Export to Excel
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require PhpSpreadsheet or similar library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';
ob_end_flush();
?>