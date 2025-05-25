<?php
/**
 * Price Impact Profit/Loss Report
 * 
 * A simplified report to analyze the impact of price changes on current stock value.
 */

// Set page title
$page_title = "Price Impact Profit/Loss Report";

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

// Get latest price changes
function getLatestPriceChanges($conn) {
    $sql = "SELECT 
                fp.price_id,
                fp.fuel_type_id,
                ft.fuel_name,
                fp.effective_date,
                fp.purchase_price,
                fp.selling_price,
                fp.profit_margin,
                fp.profit_percentage,
                u.full_name as set_by,
                (SELECT selling_price 
                 FROM fuel_prices 
                 WHERE fuel_type_id = fp.fuel_type_id 
                 AND effective_date < fp.effective_date 
                 ORDER BY effective_date DESC LIMIT 1) as previous_selling_price,
                (SELECT purchase_price 
                 FROM fuel_prices 
                 WHERE fuel_type_id = fp.fuel_type_id 
                 AND effective_date < fp.effective_date 
                 ORDER BY effective_date DESC LIMIT 1) as previous_purchase_price
            FROM 
                fuel_prices fp
                JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
                JOIN users u ON fp.set_by = u.user_id
            WHERE 
                fp.status = 'active'
            ORDER BY 
                fp.effective_date DESC, ft.fuel_name ASC";
    
    $result = $conn->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // If no previous price, set to current price (for first-time prices)
        if (!$row['previous_selling_price']) {
            $row['previous_selling_price'] = $row['selling_price'];
        }
        if (!$row['previous_purchase_price']) {
            $row['previous_purchase_price'] = $row['purchase_price'];
        }
        
        $data[] = $row;
    }
    
    return $data;
}

// Get tank inventory and calculate price impact
function getPriceImpactData($conn) {
    $sql = "SELECT 
                t.tank_id,
                t.tank_name,
                ft.fuel_type_id,
                ft.fuel_name,
                t.current_volume,
                fp.selling_price as current_price,
                (SELECT selling_price 
                 FROM fuel_prices 
                 WHERE fuel_type_id = ft.fuel_type_id 
                 AND effective_date < fp.effective_date 
                 ORDER BY effective_date DESC LIMIT 1) as previous_price,
                fp.effective_date as price_change_date
            FROM 
                tanks t
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
                LEFT JOIN (
                    SELECT 
                        fp.fuel_type_id, 
                        fp.selling_price, 
                        fp.effective_date
                    FROM 
                        fuel_prices fp
                    WHERE 
                        fp.status = 'active'
                    GROUP BY 
                        fp.fuel_type_id
                ) fp ON ft.fuel_type_id = fp.fuel_type_id
            WHERE 
                t.status = 'active'
            ORDER BY 
                t.tank_name ASC";
    
    $result = $conn->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // If no previous price, set to current price (for first-time prices)
        if (!$row['previous_price']) {
            $row['previous_price'] = $row['current_price'];
        }
        
        // Calculate value impact
        $row['current_value'] = $row['current_volume'] * $row['current_price'];
        $row['previous_value'] = $row['current_volume'] * $row['previous_price'];
        $row['value_change'] = $row['current_value'] - $row['previous_value'];
        
        $data[] = $row;
    }
    
    return $data;
}

// Calculate summary statistics
function calculateSummaryStats($impact_data) {
    $summary = [
        'total_current_value' => 0,
        'total_previous_value' => 0,
        'total_value_change' => 0,
        'positive_impact_count' => 0,
        'negative_impact_count' => 0,
        'total_positive_impact' => 0,
        'total_negative_impact' => 0
    ];
    
    foreach ($impact_data as $item) {
        $summary['total_current_value'] += $item['current_value'];
        $summary['total_previous_value'] += $item['previous_value'];
        $summary['total_value_change'] += $item['value_change'];
        
        if ($item['value_change'] > 0) {
            $summary['positive_impact_count']++;
            $summary['total_positive_impact'] += $item['value_change'];
        } elseif ($item['value_change'] < 0) {
            $summary['negative_impact_count']++;
            $summary['total_negative_impact'] += abs($item['value_change']);
        }
    }
    
    return $summary;
}

// Fetch data for the report
$price_changes = getLatestPriceChanges($conn);
$impact_data = getPriceImpactData($conn);
$summary_stats = calculateSummaryStats($impact_data);

?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Price Impact Profit/Loss Report</h2>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Current Stock Value -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="text-sm font-medium text-blue-800 mb-1">Current Stock Value</div>
            <div class="text-2xl font-bold text-blue-900"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_current_value'], 2) ?></div>
        </div>
        
        <!-- Previous Stock Value -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="text-sm font-medium text-gray-800 mb-1">Previous Stock Value</div>
            <div class="text-2xl font-bold text-gray-900"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_previous_value'], 2) ?></div>
        </div>
        
        <!-- Net Value Change -->
        <div class="<?= $summary_stats['total_value_change'] >= 0 ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100' ?> rounded-lg p-4 border">
            <div class="<?= $summary_stats['total_value_change'] >= 0 ? 'text-green-800' : 'text-red-800' ?> text-sm font-medium mb-1">Net Value Change</div>
            <div class="<?= $summary_stats['total_value_change'] >= 0 ? 'text-green-900' : 'text-red-900' ?> text-2xl font-bold">
                <?= ($summary_stats['total_value_change'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_value_change'], 2) ?>
            </div>
        </div>
        
        <!-- Profit/Loss Breakdown -->
        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-100">
            <div class="text-sm font-medium text-yellow-800 mb-1">Profit/Loss Breakdown</div>
            <div class="flex justify-between items-center">
                <div class="text-green-700">
                    <span class="text-xs">Profit:</span>
                    <span class="font-medium ml-1"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_positive_impact'], 2) ?></span>
                </div>
                <div class="text-red-700">
                    <span class="text-xs">Loss:</span>
                    <span class="font-medium ml-1"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_negative_impact'], 2) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Latest Price Changes Section -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Latest Price Changes</h3>
    
    <?php if (empty($price_changes)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800 mb-6">
        <p class="font-medium">No price changes found in the system.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto mb-6">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Date</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Previous Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Change</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">% Change</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Set By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                // Only show up to 5 recent price changes to keep report concise
                $recent_changes = array_slice($price_changes, 0, 5);
                
                foreach ($recent_changes as $change): 
                    $price_diff = $change['selling_price'] - $change['previous_selling_price'];
                    $price_percent = $change['previous_selling_price'] > 0 ? 
                                    ($price_diff / $change['previous_selling_price']) * 100 : 0;
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($change['fuel_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-600"><?= date('M d, Y', strtotime($change['effective_date'])) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($change['selling_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($change['previous_selling_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm <?= $price_diff >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium text-right">
                            <?= ($price_diff >= 0 ? '+' : '') . CURRENCY_SYMBOL . ' ' . number_format($price_diff, 2) ?>
                        </td>
                        <td class="py-3 px-4 text-sm <?= $price_diff >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium text-right">
                            <?= ($price_diff >= 0 ? '+' : '') . number_format($price_percent, 2) ?>%
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600"><?= htmlspecialchars($change['set_by']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Stock Value Impact Section -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Stock Value Impact Analysis</h3>
    
    <?php if (empty($impact_data)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800">
        <p class="font-medium">No stock data found for impact analysis.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Tank</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Stock (L)</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Previous Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Value</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Previous Value</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Impact</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($impact_data as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($item['tank_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($item['fuel_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($item['current_volume'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($item['current_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($item['previous_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($item['current_value'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($item['previous_value'], 2) ?></td>
                        <td class="py-3 px-4 text-sm <?= $item['value_change'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium text-right">
                            <?= ($item['value_change'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . ' ' . number_format($item['value_change'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <th colspan="5" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <td class="py-3 px-4 text-right text-sm font-bold text-gray-800"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_current_value'], 2) ?></td>
                    <td class="py-3 px-4 text-right text-sm font-bold text-gray-800"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_previous_value'], 2) ?></td>
                    <td class="py-3 px-4 text-right text-sm font-bold <?= $summary_stats['total_value_change'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ($summary_stats['total_value_change'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_value_change'], 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Analysis Summary -->
    <div class="mt-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
        <h4 class="font-medium text-gray-800 mb-2">Analysis Summary</h4>
        
        <?php if ($summary_stats['total_value_change'] > 0): ?>
        <p class="text-sm text-gray-700 mb-2">
            The recent price changes have resulted in a <span class="text-green-600 font-medium">positive impact of <?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_value_change'], 2) ?></span> 
            on the total fuel stock value. This represents a profit on existing inventory.
        </p>
        <?php elseif ($summary_stats['total_value_change'] < 0): ?>
        <p class="text-sm text-gray-700 mb-2">
            The recent price changes have resulted in a <span class="text-red-600 font-medium">negative impact of <?= CURRENCY_SYMBOL . ' ' . number_format(abs($summary_stats['total_value_change']), 2) ?></span> 
            on the total fuel stock value. This represents a loss on existing inventory.
        </p>
        <?php else: ?>
        <p class="text-sm text-gray-700 mb-2">
            The recent price changes have had no significant impact on the total fuel stock value.
        </p>
        <?php endif; ?>
        
        <div class="flex flex-col sm:flex-row sm:justify-between gap-4 mt-3">
            <div class="bg-white p-3 rounded border border-gray-200">
                <div class="text-xs text-gray-500 mb-1">Tanks with price increase:</div>
                <div class="text-gray-800 font-medium"><?= $summary_stats['positive_impact_count'] ?> tanks</div>
                <div class="text-green-600 text-sm"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_positive_impact'], 2) ?> profit</div>
            </div>
            
            <div class="bg-white p-3 rounded border border-gray-200">
                <div class="text-xs text-gray-500 mb-1">Tanks with price decrease:</div>
                <div class="text-gray-800 font-medium"><?= $summary_stats['negative_impact_count'] ?> tanks</div>
                <div class="text-red-600 text-sm"><?= CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_negative_impact'], 2) ?> loss</div>
            </div>
            
            <div class="bg-white p-3 rounded border border-gray-200">
                <div class="text-xs text-gray-500 mb-1">Net stock value change:</div>
                <div class="<?= $summary_stats['total_value_change'] >= 0 ? 'text-green-600' : 'text-red-600' ?> font-medium">
                    <?= ($summary_stats['total_value_change'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . ' ' . number_format($summary_stats['total_value_change'], 2) ?>
                </div>
                <div class="text-gray-600 text-sm">
                    <?php
                    $percent_change = $summary_stats['total_previous_value'] > 0 ? 
                                     ($summary_stats['total_value_change'] / $summary_stats['total_previous_value']) * 100 : 0;
                    echo ($percent_change >= 0 ? '+' : '') . number_format($percent_change, 2) . '% change';
                    ?>
                </div>
            </div>
        </div>
    </div>
    
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
?>