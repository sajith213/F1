<?php
/**
 * Fuel Capacity Status Report
 * 
 * A simplified report to monitor current stock levels of each tank by fuel type.
 */
ob_start();
// Set page title
$page_title = "Fuel Capacity Status Report";

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
$fuel_type_id = isset($_GET['fuel_type_id']) ? $_GET['fuel_type_id'] : null;
$tank_id = isset($_GET['tank_id']) ? $_GET['tank_id'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel_type_id = !empty($_POST['fuel_type_id']) ? $_POST['fuel_type_id'] : null;
    $tank_id = !empty($_POST['tank_id']) ? $_POST['tank_id'] : null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'fuel_type_id' => $fuel_type_id,
        'tank_id' => $tank_id
    ]);
    
    header("Location: fuel_capacity_report.php?$query");
    exit;
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

// Fetch tanks for dropdown
function getTanks($conn) {
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

// Get tank capacity data
function getTankCapacityData($conn, $fuel_type_id = null, $tank_id = null) {
    $sql = "SELECT 
                t.tank_id,
                t.tank_name,
                ft.fuel_type_id,
                ft.fuel_name,
                t.capacity,
                t.current_volume,
                t.low_level_threshold,
                (t.current_volume / t.capacity) * 100 as fill_percentage,
                (SELECT fp.selling_price 
                 FROM fuel_prices fp 
                 WHERE fp.fuel_type_id = ft.fuel_type_id 
                 AND fp.status = 'active'
                 ORDER BY fp.effective_date DESC LIMIT 1) as current_price,
                CASE 
                    WHEN t.current_volume <= t.low_level_threshold THEN 'critical'
                    WHEN t.current_volume < (t.capacity * 0.3) THEN 'low'
                    WHEN t.current_volume < (t.capacity * 0.6) THEN 'medium'
                    ELSE 'high'
                END as status
            FROM 
                tanks t
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            WHERE 
                t.status = 'active'";
    
    $params = [];
    $types = "";
    
    if ($fuel_type_id) {
        $sql .= " AND ft.fuel_type_id = ?";
        $params[] = $fuel_type_id;
        $types .= "i";
    }
    
    if ($tank_id) {
        $sql .= " AND t.tank_id = ?";
        $params[] = $tank_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY status ASC, fill_percentage ASC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Calculate summary statistics
function calculateSummaryStats($tank_data) {
    $summary = [
        'total_tanks' => count($tank_data),
        'total_capacity' => 0,
        'total_volume' => 0,
        'average_fill' => 0,
        'critical_tanks' => 0,
        'low_tanks' => 0,
        'medium_tanks' => 0,
        'high_tanks' => 0,
        'status_counts' => [
            'critical' => 0,
            'low' => 0,
            'medium' => 0,
            'high' => 0
        ]
    ];
    
    foreach ($tank_data as $tank) {
        $summary['total_capacity'] += $tank['capacity'];
        $summary['total_volume'] += $tank['current_volume'];
        $summary['status_counts'][$tank['status']]++;
    }
    
    $summary['average_fill'] = $summary['total_capacity'] > 0 ? 
                              ($summary['total_volume'] / $summary['total_capacity']) * 100 : 0;
    
    return $summary;
}

// Get data for the report
$fuel_types = getFuelTypes($conn);
$tanks_list = getTanks($conn);
$tank_data = getTankCapacityData($conn, $fuel_type_id, $tank_id);
$summary_stats = calculateSummaryStats($tank_data);

// Calculate needed fuel to reach full capacity
function calculateRefillNeeds($tank) {
    return $tank['capacity'] - $tank['current_volume'];
}

// Get refill recommendations for critical and low tanks
function getRefillRecommendations($tank_data) {
    $recommendations = [];
    
    foreach ($tank_data as $tank) {
        if ($tank['status'] === 'critical' || $tank['status'] === 'low') {
            $refill_amount = calculateRefillNeeds($tank);
            $recommendations[] = [
                'tank_id' => $tank['tank_id'],
                'tank_name' => $tank['tank_name'],
                'fuel_name' => $tank['fuel_name'],
                'current_volume' => $tank['current_volume'],
                'refill_amount' => $refill_amount,
                'status' => $tank['status'],
                'estimated_cost' => $refill_amount * ($tank['current_price'] ?? 0)
            ];
        }
    }
    
    return $recommendations;
}

$refill_recommendations = getRefillRecommendations($tank_data);

?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Fuel Capacity Status Report</h2>
    
    <!-- Filter Form -->
    <form method="POST" action="" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
            
            <!-- Tank Filter -->
            <div>
                <label for="tank_id" class="block text-sm font-medium text-gray-700 mb-1">Tank</label>
                <select id="tank_id" name="tank_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Tanks</option>
                    <?php foreach ($tanks_list as $tank): ?>
                    <option value="<?= $tank['tank_id'] ?>" <?= $tank_id == $tank['tank_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tank['tank_name'] . ' (' . $tank['fuel_name'] . ')') ?>
                    </option>
                    <?php endforeach; ?>
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
        <!-- Total Capacity -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="text-sm font-medium text-blue-800 mb-1">Total Capacity</div>
            <div class="text-2xl font-bold text-blue-900"><?= number_format($summary_stats['total_capacity'], 2) ?> L</div>
        </div>
        
        <!-- Current Stock -->
        <div class="bg-green-50 rounded-lg p-4 border border-green-100">
            <div class="text-sm font-medium text-green-800 mb-1">Current Stock</div>
            <div class="text-2xl font-bold text-green-900"><?= number_format($summary_stats['total_volume'], 2) ?> L</div>
        </div>
        
        <!-- Average Fill Level -->
        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100">
            <div class="text-sm font-medium text-indigo-800 mb-1">Average Fill Level</div>
            <div class="text-2xl font-bold text-indigo-900"><?= number_format($summary_stats['average_fill'], 1) ?>%</div>
        </div>
        
        <!-- Tank Status -->
        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-100">
            <div class="text-sm font-medium text-yellow-800 mb-1">Tanks Needing Refill</div>
            <div class="text-2xl font-bold text-yellow-900">
                <?= $summary_stats['status_counts']['critical'] + $summary_stats['status_counts']['low'] ?>
                <span class="text-sm font-normal text-yellow-700">of <?= $summary_stats['total_tanks'] ?> tanks</span>
            </div>
        </div>
    </div>
    
    <!-- Tank Capacity Table -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Tank Capacity Status</h3>
    
    <?php if (empty($tank_data)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800">
        <p class="font-medium">No tanks found for the selected criteria.</p>
        <p class="text-sm mt-1">Try adjusting your filters.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Tank Name</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Capacity (L)</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current (L)</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Empty Space (L)</th>
                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fill Level</th>
                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($tank_data as $tank): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($tank['tank_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($tank['fuel_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($tank['capacity'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($tank['current_volume'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($tank['capacity'] - $tank['current_volume'], 2) ?></td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="<?php 
                                    if ($tank['status'] === 'critical') echo 'bg-red-600';
                                    elseif ($tank['status'] === 'low') echo 'bg-yellow-500';
                                    elseif ($tank['status'] === 'medium') echo 'bg-blue-500';
                                    else echo 'bg-green-600';
                                ?> h-2.5 rounded-full" style="width: <?= min(100, $tank['fill_percentage']) ?>%"></div>
                            </div>
                            <div class="text-xs text-center mt-1"><?= number_format($tank['fill_percentage'], 1) ?>%</div>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if ($tank['status'] === 'critical'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Critical
                                </span>
                            <?php elseif ($tank['status'] === 'low'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Low
                                </span>
                            <?php elseif ($tank['status'] === 'medium'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Medium
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Good
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Refill Recommendations Section -->
    <?php if (!empty($refill_recommendations)): ?>
    <h3 class="text-lg font-semibold text-gray-800 mb-3 mt-6">Refill Recommendations</h3>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
        <p class="text-yellow-800 font-medium mb-2">The following tanks need refilling:</p>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Tank Name</th>
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Volume (L)</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Recommended Refill (L)</th>
                        <th class="py-2 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($refill_recommendations as $rec): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 text-sm text-gray-800"><?= htmlspecialchars($rec['tank_name']) ?></td>
                            <td class="py-2 px-4 text-sm text-gray-800"><?= htmlspecialchars($rec['fuel_name']) ?></td>
                            <td class="py-2 px-4 text-sm text-gray-800 text-right"><?= number_format($rec['current_volume'], 2) ?></td>
                            <td class="py-2 px-4 text-sm text-gray-800 text-right font-medium"><?= number_format($rec['refill_amount'], 2) ?></td>
                            <td class="py-2 px-4 text-center">
                                <?php if ($rec['status'] === 'critical'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Urgent Refill
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Plan Refill
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
ob_end_flush();
?>