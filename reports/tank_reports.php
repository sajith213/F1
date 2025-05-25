<?php
// Start output buffering
ob_start();

/**
 * Tank Reports
 * 
 * This file generates various reports related to fuel tank management.
 */

// Set page title
$page_title = "Tank Reports";

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
$report_type = isset($_GET['type']) ? $_GET['type'] : 'status';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'status';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $tank_id = $_POST['tank_id'] ?? null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'tank_id' => $tank_id
    ]);
    
    header("Location: tank_reports.php?$query");
    exit;
}

// Get tank selection from URL
$tank_id = isset($_GET['tank_id']) ? $_GET['tank_id'] : null;

// Function to get all tanks for selection dropdown
function getAllTanks($conn) {
    $sql = "SELECT 
                t.tank_id,
                t.tank_name,
                ft.fuel_name
            FROM 
                tanks t
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            ORDER BY 
                t.tank_name";
    
    $result = $conn->query($sql);
    
    $tanks = [];
    while ($row = $result->fetch_assoc()) {
        $tanks[] = $row;
    }
    
    return $tanks;
}

// Function to get tank status data
function getTankStatusData($conn, $tank_id = null) {
    $sql = "SELECT 
                t.tank_id,
                t.tank_name,
                ft.fuel_name,
                t.capacity,
                t.current_volume,
                (t.current_volume / t.capacity) * 100 as percentage_full,
                t.low_level_threshold,
                CASE 
                    WHEN t.current_volume <= t.low_level_threshold THEN 'low'
                    WHEN t.current_volume < (t.capacity * 0.5) THEN 'medium'
                    ELSE 'good'
                END as status
            FROM 
                tanks t
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id";
    
    if ($tank_id) {
        $sql .= " WHERE t.tank_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tank_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql .= " ORDER BY percentage_full ASC";
        $result = $conn->query($sql);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get tank inventory history
function getTankInventoryHistory($conn, $tank_id, $start_date, $end_date) {
    $sql = "SELECT 
                ti.inventory_id,
                ti.operation_date,
                ti.operation_type,
                ti.previous_volume,
                ti.change_amount,
                ti.new_volume,
                ti.notes,
                u.full_name as recorded_by,
                CASE 
                    WHEN ti.reference_id IS NOT NULL AND ti.operation_type = 'delivery' THEN fd.delivery_reference
                    WHEN ti.reference_id IS NOT NULL AND ti.operation_type = 'transfer' THEN CONCAT('Transfer #', ti.reference_id)
                    ELSE NULL
                END as reference
            FROM 
                tank_inventory ti
                JOIN users u ON ti.recorded_by = u.user_id
                LEFT JOIN fuel_deliveries fd ON ti.reference_id = fd.delivery_id AND ti.operation_type = 'delivery'
            WHERE 
                ti.tank_id = ? AND
                DATE(ti.operation_date) BETWEEN ? AND ?
            ORDER BY 
                ti.operation_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $tank_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get daily consumption for a tank
function getTankDailyConsumption($conn, $tank_id, $start_date, $end_date) {
    $sql = "SELECT 
                DATE(ti.operation_date) as date,
                SUM(CASE WHEN ti.operation_type = 'sales' THEN ABS(ti.change_amount) ELSE 0 END) as sales_volume,
                SUM(CASE WHEN ti.operation_type = 'delivery' THEN ti.change_amount ELSE 0 END) as delivery_volume
            FROM 
                tank_inventory ti
            WHERE 
                ti.tank_id = ? AND
                DATE(ti.operation_date) BETWEEN ? AND ?
            GROUP BY 
                DATE(ti.operation_date)
            ORDER BY 
                DATE(ti.operation_date)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $tank_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Fetch all tanks for dropdown
$all_tanks = getAllTanks($conn);

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'history':
        if ($tank_id) {
            $report_data = getTankInventoryHistory($conn, $tank_id, $start_date, $end_date);
            
            // Get tank name for title
            $tank_info = array_filter($all_tanks, function($tank) use ($tank_id) {
                return $tank['tank_id'] == $tank_id;
            });
            
            $tank_info = reset($tank_info);
            $report_title = "Tank Inventory History - " . htmlspecialchars($tank_info['tank_name']);
        } else {
            $report_title = "Please select a tank to view inventory history";
        }
        break;
        
    case 'consumption':
        if ($tank_id) {
            $report_data = getTankDailyConsumption($conn, $tank_id, $start_date, $end_date);
            
            // Get tank name for title
            $tank_info = array_filter($all_tanks, function($tank) use ($tank_id) {
                return $tank['tank_id'] == $tank_id;
            });
            
            $tank_info = reset($tank_info);
            $report_title = "Daily Consumption - " . htmlspecialchars($tank_info['tank_name']);
        } else {
            $report_title = "Please select a tank to view consumption data";
        }
        break;
        
    case 'status':
    default:
        $report_data = getTankStatusData($conn, $tank_id);
        $report_title = $tank_id ? "Tank Status - Selected Tank" : "Tank Status Overview";
        break;
}
?>

<!-- Filter form -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="POST" action="" class="w-full">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="status" <?= $report_type === 'status' ? 'selected' : '' ?>>Tank Status</option>
                    <option value="history" <?= $report_type === 'history' ? 'selected' : '' ?>>Inventory History</option>
                    <option value="consumption" <?= $report_type === 'consumption' ? 'selected' : '' ?>>Daily Consumption</option>
                </select>
            </div>
            
            <div>
                <label for="tank_id" class="block text-sm font-medium text-gray-700 mb-1">Select Tank</label>
                <select id="tank_id" name="tank_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Tanks</option>
                    <?php foreach ($all_tanks as $tank): ?>
                        <option value="<?= $tank['tank_id'] ?>" <?= $tank_id == $tank['tank_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tank['tank_name']) ?> (<?= htmlspecialchars($tank['fuel_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" <?= $report_type === 'status' ? 'disabled' : '' ?>>
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" <?= $report_type === 'status' ? 'disabled' : '' ?>>
            </div>
            
            <div class="flex items-end">
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
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0"><?= $report_title ?></h2>
        
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
    <?php if (empty($report_data) && ($report_type === 'history' || $report_type === 'consumption') && !$tank_id): ?>
        <div class="p-8 text-center">
            <div class="bg-blue-50 rounded-lg p-6 inline-block">
                <i class="fas fa-info-circle text-blue-500 text-3xl mb-3"></i>
                <h3 class="text-lg font-medium text-blue-900 mb-2">Select a Tank</h3>
                <p class="text-blue-700">Please select a specific tank from the dropdown menu to view detailed information.</p>
            </div>
        </div>
    <?php elseif (empty($report_data)): ?>
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
            <p>No data found for the selected criteria.</p>
        </div>
    <?php else: ?>
        <?php if ($report_type === 'status'): ?>
            <!-- Tank Status Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tank</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current (L)</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Fill Level</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Low Level Threshold</th>
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['low_level_threshold'], 2) ?>
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
            
        <?php elseif ($report_type === 'history'): ?>
            <!-- Tank Inventory History Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operation</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Previous (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Change (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">New (L)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y h:i A', strtotime($row['operation_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $badge_color = '';
                                    switch($row['operation_type']) {
                                        case 'delivery':
                                            $badge_color = 'bg-green-100 text-green-800';
                                            break;
                                        case 'sales':
                                            $badge_color = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'adjustment':
                                            $badge_color = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'leak':
                                            $badge_color = 'bg-red-100 text-red-800';
                                            break;
                                        case 'transfer':
                                            $badge_color = 'bg-purple-100 text-purple-800';
                                            break;
                                        default:
                                            $badge_color = 'bg-gray-100 text-gray-800';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $badge_color ?>">
                                        <?= ucfirst($row['operation_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $row['reference'] ? htmlspecialchars($row['reference']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['previous_volume'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium <?= $row['change_amount'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= ($row['change_amount'] >= 0 ? '+' : '') . number_format($row['change_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?= number_format($row['new_volume'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['recorded_by']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                    <?= $row['notes'] ? htmlspecialchars($row['notes']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_type === 'consumption'): ?>
            <!-- Daily Consumption Report -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Volume (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery Volume (L)</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Change (L)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $total_sales = 0;
                        $total_delivery = 0;
                        
                        foreach ($report_data as $row): 
                            $net_change = $row['delivery_volume'] - $row['sales_volume'];
                            $total_sales += $row['sales_volume'];
                            $total_delivery += $row['delivery_volume'];
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($row['date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                    <?= number_format($row['sales_volume'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                    <?= number_format($row['delivery_volume'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right <?= $net_change >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                    <?= ($net_change >= 0 ? '+' : '') . number_format($net_change, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <td class="px-6 py-3 text-right text-xs font-medium text-red-600"><?= number_format($total_sales, 2) ?></td>
                            <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= number_format($total_delivery, 2) ?></td>
                            <td class="px-6 py-3 text-right text-xs font-medium <?= ($total_delivery - $total_sales) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= ($total_delivery - $total_sales) >= 0 ? '+' : '' ?><?= number_format($total_delivery - $total_sales, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
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
        if (this.value === 'status') {
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

// End output buffering and send output
ob_end_flush();
?>