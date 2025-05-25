<?php
/**
 * Pump Reports
 * 
 * This file generates various reports related to fuel pump operations.
 */

// Set page title
$page_title = "Pump Reports";

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
$report_type = isset($_GET['type']) ? $_GET['type'] : 'performance';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get pump selection from URL
$pump_id = isset($_GET['pump_id']) ? $_GET['pump_id'] : null;

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'performance';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $pump_id = $_POST['pump_id'] ?? null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'pump_id' => $pump_id
    ]);
    
    header("Location: pump_reports.php?$query");
    exit;
}

// Function to get all pumps for selection dropdown
function getAllPumps($conn) {
    $sql = "SELECT 
                p.pump_id,
                p.pump_name,
                t.tank_name,
                ft.fuel_name
            FROM 
                pumps p
                JOIN tanks t ON p.tank_id = t.tank_id
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            ORDER BY 
                p.pump_name";
    
    $result = $conn->query($sql);
    
    $pumps = [];
    while ($row = $result->fetch_assoc()) {
        $pumps[] = $row;
    }
    
    return $pumps;
}

// Function to get pump performance data
function getPumpPerformanceData($conn, $start_date, $end_date, $pump_id = null) {
    $sql = "SELECT 
                p.pump_id,
                p.pump_name,
                ft.fuel_name,
                COUNT(DISTINCT mr.reading_id) as total_readings,
                SUM(mr.volume_dispensed) as total_volume,
                SUM(si.net_amount) as total_sales,
                COUNT(DISTINCT si.sale_id) as total_transactions
            FROM 
                pumps p
                JOIN pump_nozzles pn ON p.pump_id = pn.pump_id
                JOIN meter_readings mr ON pn.nozzle_id = mr.nozzle_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
                LEFT JOIN sale_items si ON pn.nozzle_id = si.nozzle_id AND si.item_type = 'fuel'
                LEFT JOIN sales s ON si.sale_id = s.sale_id AND DATE(s.sale_date) BETWEEN ? AND ?
            WHERE 
                mr.reading_date BETWEEN ? AND ?";
    
    if ($pump_id) {
        $sql .= " AND p.pump_id = ?";
    }
    
    $sql .= " GROUP BY p.pump_id
              ORDER BY total_volume DESC";
    
    if ($pump_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $start_date, $end_date, $start_date, $end_date, $pump_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get daily meter readings for a specific pump
function getDailyMeterReadings($conn, $pump_id, $start_date, $end_date) {
    $sql = "SELECT 
                mr.reading_date,
                pn.nozzle_number,
                ft.fuel_name,
                mr.opening_reading,
                mr.closing_reading,
                mr.volume_dispensed,
                mr.notes,
                u.full_name as recorded_by
            FROM 
                meter_readings mr
                JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
                JOIN pumps p ON pn.pump_id = p.pump_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
                JOIN users u ON mr.recorded_by = u.user_id
            WHERE 
                p.pump_id = ? AND
                mr.reading_date BETWEEN ? AND ?
            ORDER BY 
                mr.reading_date DESC, pn.nozzle_number ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $pump_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get pump maintenance history
function getPumpMaintenanceHistory($conn, $pump_id = null) {
    // This would require a maintenance table which isn't in the current schema
    // For now, return a placeholder message
    return "Maintenance records not available in current database schema";
}

// Fetch all pumps for dropdown
$all_pumps = getAllPumps($conn);

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'readings':
        if ($pump_id) {
            $report_data = getDailyMeterReadings($conn, $pump_id, $start_date, $end_date);
            
            // Get pump name for title
            $pump_info = array_filter($all_pumps, function($pump) use ($pump_id) {
                return $pump['pump_id'] == $pump_id;
            });
            
            $pump_info = reset($pump_info);
            $report_title = "Meter Readings - " . htmlspecialchars($pump_info['pump_name']);
        } else {
            $report_title = "Please select a pump to view meter readings";
        }
        break;
        
    case 'maintenance':
        if ($pump_id) {
            $report_data = getPumpMaintenanceHistory($conn, $pump_id);
            
            // Get pump name for title
            $pump_info = array_filter($all_pumps, function($pump) use ($pump_id) {
                return $pump['pump_id'] == $pump_id;
            });
            
            $pump_info = reset($pump_info);
            $report_title = "Maintenance History - " . htmlspecialchars($pump_info['pump_name']);
        } else {
            $report_title = "Please select a pump to view maintenance history";
        }
        break;
        
    case 'performance':
    default:
        $report_data = getPumpPerformanceData($conn, $start_date, $end_date, $pump_id);
        $report_title = $pump_id ? "Pump Performance - Selected Pump" : "Pump Performance Overview";
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
                    <option value="performance" <?= $report_type === 'performance' ? 'selected' : '' ?>>Pump Performance</option>
                    <option value="readings" <?= $report_type === 'readings' ? 'selected' : '' ?>>Meter Readings</option>
                    <option value="maintenance" <?= $report_type === 'maintenance' ? 'selected' : '' ?>>Maintenance History</option>
                </select>
            </div>
            
            <div>
                <label for="pump_id" class="block text-sm font-medium text-gray-700 mb-1">Select Pump</label>
                <select id="pump_id" name="pump_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Pumps</option>
                    <?php foreach ($all_pumps as $pump): ?>
                        <option value="<?= $pump['pump_id'] ?>" <?= $pump_id == $pump['pump_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pump['pump_name']) ?> (<?= htmlspecialchars($pump['fuel_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
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
    <?php if (empty($report_data) && ($report_type === 'readings' || $report_type === 'maintenance') && !$pump_id): ?>
        <div class="p-8 text-center">
            <div class="bg-blue-50 rounded-lg p-6 inline-block">
                <i class="fas fa-info-circle text-blue-500 text-3xl mb-3"></i>
                <h3 class="text-lg font-medium text-blue-900 mb-2">Select a Pump</h3>
                <p class="text-blue-700">Please select a specific pump from the dropdown menu to view detailed information.</p>
            </div>
        </div>
    <?php elseif (empty($report_data) && is_array($report_data)): ?>
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
            <p>No data found for the selected criteria.</p>
        </div>
    <?php elseif ($report_type === 'performance'): ?>
        <!-- Pump Performance Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed (L)</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Transaction</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $total_volume = array_sum(array_column($report_data, 'total_volume') ?: [0]);
                    $total_sales = array_sum(array_column($report_data, 'total_sales') ?: [0]);
                    
                    foreach ($report_data as $row): 
                        $avg_transaction = $row['total_transactions'] > 0 ? ($row['total_sales'] ?? 0) / $row['total_transactions'] : 0;
                        $performance_percentage = $total_volume > 0 ? (($row['total_volume'] ?? 0) / $total_volume) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($row['pump_name']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['fuel_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_volume'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['total_sales'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_transactions'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($avg_transaction ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $performance_percentage ?? 0 ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($performance_percentage ?? 0, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($total_volume ?? 0, 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($total_sales ?? 0, 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'total_transactions') ?: [0])) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">
                            <?php
                            $total_transactions = array_sum(array_column($report_data, 'total_transactions') ?: [0]);
                            $avg_total = $total_transactions > 0 ? $total_sales / $total_transactions : 0;
                            echo CURRENCY_SYMBOL . number_format($avg_total ?? 0, 2);
                            ?>
                        </td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'readings'): ?>
        <!-- Meter Readings Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Opening Reading</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Reading</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed (L)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y', strtotime($row['reading_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= $row['nozzle_number'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['fuel_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['opening_reading'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['closing_reading'] ?? 0, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= number_format($row['volume_dispensed'] ?? 0, 2) ?>
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
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="5" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Volume Dispensed</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format(array_sum(array_column($report_data, 'volume_dispensed') ?: [0]), 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    
    <?php elseif ($report_type === 'maintenance'): ?>
        <!-- Maintenance History Report -->
        <?php if (is_string($report_data)): ?>
            <div class="p-8 text-center">
                <div class="bg-yellow-50 rounded-lg p-6 inline-block">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>
                    <h3 class="text-lg font-medium text-yellow-900 mb-2">Feature Not Available</h3>
                    <p class="text-yellow-700">Maintenance records functionality is not available in the current system configuration.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- This would display maintenance history data if available -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <!-- Table content would go here -->
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- JavaScript for export functionality -->
<script>
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
?>