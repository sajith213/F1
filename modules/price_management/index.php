<?php
/**
 * Price Management Dashboard
 * 
 * Main page for price management showing current prices and recent price changes
 */

// Set page title and include header
$page_title = "Price Management";
$breadcrumbs = "<a href='../../index.php'>Home</a> / Price Management";

// Include header and sidebar
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check user permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <strong>Error!</strong> You don't have permission to access this page.
          </div>";
    include '../../includes/footer.php';
    exit;
}

// Get current fuel prices
$current_prices = getCurrentFuelPrices();

// Get planned fuel prices
$planned_prices = getPlannedFuelPrices();

// Get recent price impact data
$recent_impacts = getPriceChangeImpact();
?>

<div class="flex flex-col md:flex-row gap-4">
    <!-- Left Column -->
    <div class="w-full md:w-2/3">
        <!-- Current Prices Card -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-4">
            <div class="bg-blue-600 px-4 py-3">
                <div class="flex justify-between items-center">
                    <h2 class="text-white text-lg font-semibold">Current Fuel Prices</h2>
                    <a href="add_price.php" class="bg-white text-blue-600 hover:bg-blue-100 rounded-md px-4 py-1 text-sm font-medium">
                        Add New Price
                    </a>
                </div>
            </div>
            
            <div class="p-4">
                <?php if (empty($current_prices)): ?>
                    <div class="text-center py-4 text-gray-500">
                        No active prices found. <a href="add_price.php" class="text-blue-600 hover:underline">Add your first price</a>.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fuel Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Purchase Price
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Selling Price
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Profit Margin
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Effective Date
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($current_prices as $price): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">
                                                <?= htmlspecialchars($price['fuel_name']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-gray-700">
                                                <?= formatPrice($price['purchase_price']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">
                                                <?= formatPrice($price['selling_price']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-green-600">
                                                <?= formatPrice($price['profit_margin']) ?> 
                                                (<?= formatPercentage($price['profit_percentage']) ?>)
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                            <?= date('M d, Y', strtotime($price['effective_date'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Planned Prices Card -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-4">
            <div class="bg-indigo-600 px-4 py-3">
                <h2 class="text-white text-lg font-semibold">Upcoming Price Changes</h2>
            </div>
            
            <div class="p-4">
                <?php if (empty($planned_prices)): ?>
                    <div class="text-center py-4 text-gray-500">
                        No upcoming price changes planned.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fuel Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Current Price
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        New Price
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Effective Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $currentPriceByFuel = [];
                                foreach ($current_prices as $price) {
                                    $currentPriceByFuel[$price['fuel_type_id']] = $price['selling_price'];
                                }
                                
                                foreach ($planned_prices as $price): 
                                    $current = $currentPriceByFuel[$price['fuel_type_id']] ?? 0;
                                    $change = $price['selling_price'] - $current;
                                    $changePercent = ($current > 0) ? ($change / $current * 100) : 0;
                                    $changeClass = ($change >= 0) ? 'text-green-600' : 'text-red-600';
                                ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">
                                                <?= htmlspecialchars($price['fuel_name']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                            <?= formatPrice($current) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium <?= $changeClass ?>">
                                                <?= formatPrice($price['selling_price']) ?>
                                                <?php if ($change != 0): ?>
                                                    <span class="text-xs ml-1">
                                                        (<?= ($change >= 0) ? '+' : '' ?><?= formatPrice($change) ?> / 
                                                        <?= ($changePercent >= 0) ? '+' : '' ?><?= formatPercentage($changePercent) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                            <?= date('M d, Y', strtotime($price['effective_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex space-x-2">
                                                <a href="update_price.php?id=<?= $price['price_id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?= $price['price_id'] ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="w-full md:w-1/3">
        <!-- Quick Actions Card -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-4">
            <div class="bg-gray-800 px-4 py-3">
                <h2 class="text-white text-lg font-semibold">Quick Actions</h2>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 gap-3">
                    <a href="add_price.php" class="bg-blue-50 hover:bg-blue-100 p-3 rounded-lg flex items-center">
                        <div class="bg-blue-600 text-white p-2 rounded-full mr-3">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-blue-600">Add New Price</h3>
                            <p class="text-sm text-gray-600">Set new fuel prices</p>
                        </div>
                    </a>
                    
                    <a href="view_prices.php" class="bg-purple-50 hover:bg-purple-100 p-3 rounded-lg flex items-center">
                        <div class="bg-purple-600 text-white p-2 rounded-full mr-3">
                            <i class="fas fa-history"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-purple-600">Price History</h3>
                            <p class="text-sm text-gray-600">View historical price changes</p>
                        </div>
                    </a>
                    
                    <a href="price_analysis.php" class="bg-green-50 hover:bg-green-100 p-3 rounded-lg flex items-center">
                        <div class="bg-green-600 text-white p-2 rounded-full mr-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-green-600">Price Impact Analysis</h3>
                            <p class="text-sm text-gray-600">Analyze impact on inventory value</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Price Changes Impact -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="bg-amber-600 px-4 py-3">
                <h2 class="text-white text-lg font-semibold">Recent Price Change Impact</h2>
            </div>
            <div class="p-4">
                <?php if (empty($recent_impacts)): ?>
                    <div class="text-center py-4 text-gray-500">
                        No recent price impact data available.
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach (array_slice($recent_impacts, 0, 5) as $impact): ?>
                            <div class="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0">
                                <div class="flex justify-between">
                                    <div class="font-medium"><?= htmlspecialchars($impact['fuel_name']) ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?= date('M d, Y', strtotime($impact['calculation_date'])) ?>
                                    </div>
                                </div>
                                <div class="mt-1 text-sm">
                                    <span class="text-gray-700">Tank:</span> 
                                    <span class="font-medium"><?= htmlspecialchars($impact['tank_name']) ?></span>
                                </div>
                                <div class="mt-1 flex justify-between">
                                    <div class="text-sm">
                                        <span class="text-gray-700">Price Change:</span>
                                        <?= formatPrice($impact['old_price']) ?> → <?= formatPrice($impact['new_price']) ?>
                                    </div>
                                    <?php 
                                        $valueClass = $impact['value_change'] >= 0 ? 'text-green-600' : 'text-red-600';
                                    ?>
                                    <div class="font-medium <?= $valueClass ?>">
                                        <?= $impact['value_change'] >= 0 ? '+' : '' ?><?= formatPrice($impact['value_change']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="price_analysis.php" class="text-amber-600 hover:underline">
                            View Full Impact Analysis <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Delete</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to delete this planned price change? This action cannot be undone.</p>
        <div class="flex justify-end space-x-4">
            <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                Cancel
            </button>
            <form id="deleteForm" method="post" action="delete_price.php">
                <input type="hidden" id="delete_price_id" name="price_id" value="">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmDelete(priceId) {
        document.getElementById('delete_price_id').value = priceId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    
    // Close modal if clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>