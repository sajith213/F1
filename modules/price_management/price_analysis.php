<?php
/**
 * Price Impact Analysis
 * 
 * This page shows the impact of price changes on inventory value
 */

// Set page title and include header
$page_title = "Price Impact Analysis";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Price Management</a> / Price Impact Analysis";

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

// Get price impact data
$impacts = getPriceChangeImpact();

// Group impacts by price change for better organization
$groupedImpacts = [];
foreach ($impacts as $impact) {
    if (!isset($groupedImpacts[$impact['price_id']])) {
        $groupedImpacts[$impact['price_id']] = [
            'fuel_name' => $impact['fuel_name'],
            'calculation_date' => $impact['calculation_date'],
            'old_price' => $impact['old_price'],
            'new_price' => $impact['new_price'],
            'calculated_by' => $impact['calculated_by_name'],
            'tanks' => [],
            'total_value_change' => 0,
            'total_stock_volume' => 0
        ];
    }
    
    $groupedImpacts[$impact['price_id']]['tanks'][] = [
        'tank_name' => $impact['tank_name'],
        'stock_volume' => $impact['stock_volume'],
        'value_change' => $impact['value_change']
    ];
    
    $groupedImpacts[$impact['price_id']]['total_value_change'] += $impact['value_change'];
    $groupedImpacts[$impact['price_id']]['total_stock_volume'] += $impact['stock_volume'];
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
    <div class="bg-amber-600 px-4 py-3">
        <h2 class="text-white text-lg font-semibold">Price Change Impact Analysis</h2>
    </div>
    
    <div class="p-4">
        <p class="text-gray-600 mb-4">
            This page shows how price changes affect the value of fuel inventory in your tanks. 
            Positive values indicate an increase in inventory value, while negative values indicate a decrease.
        </p>
        
        <?php if (empty($groupedImpacts)): ?>
            <div class="bg-gray-50 p-6 text-center text-gray-500 rounded-lg">
                <p class="text-xl mb-2">No price impact data available</p>
                <p>Price impact data will be generated automatically when active fuel prices are changed.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($groupedImpacts as $priceId => $impact): ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?= htmlspecialchars($impact['fuel_name']) ?> Price Change
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <?= date('F d, Y', strtotime($impact['calculation_date'])) ?>
                                        by <?= htmlspecialchars($impact['calculated_by']) ?>
                                    </p>
                                </div>
                                <div class="mt-2 md:mt-0">
                                    <div class="text-sm">
                                        <span class="text-gray-700">Price Change:</span>
                                        <span class="font-medium">
                                            <?= formatPrice($impact['old_price']) ?> → <?= formatPrice($impact['new_price']) ?>
                                        </span>
                                        <?php 
                                            $change = $impact['new_price'] - $impact['old_price'];
                                            $pctChange = ($impact['old_price'] > 0) ? ($change / $impact['old_price'] * 100) : 0;
                                            $changeClass = $change >= 0 ? 'text-green-600' : 'text-red-600';
                                        ?>
                                        <span class="ml-2 font-medium <?= $changeClass ?>">
                                            (<?= $change >= 0 ? '+' : '' ?><?= formatPrice($change) ?> / 
                                            <?= $pctChange >= 0 ? '+' : '' ?><?= formatPercentage($pctChange) ?>)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <!-- Total Impact Summary -->
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <span class="text-sm text-gray-600">Total Stock Volume:</span>
                                        <div class="mt-1 text-2xl font-bold text-gray-900">
                                            <?= number_format($impact['total_stock_volume'], 2) ?> liters
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600">Total Value Change:</span>
                                        <?php $totalValueClass = $impact['total_value_change'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>
                                        <div class="mt-1 text-2xl font-bold <?= $totalValueClass ?>">
                                            <?= $impact['total_value_change'] >= 0 ? '+' : '' ?><?= formatPrice($impact['total_value_change']) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600">Average Impact per Liter:</span>
                                        <?php 
                                            $avgImpact = $impact['total_stock_volume'] > 0 ? 
                                                ($impact['total_value_change'] / $impact['total_stock_volume']) : 0;
                                            $avgImpactClass = $avgImpact >= 0 ? 'text-green-600' : 'text-red-600';
                                        ?>
                                        <div class="mt-1 text-2xl font-bold <?= $avgImpactClass ?>">
                                            <?= $avgImpact >= 0 ? '+' : '' ?><?= formatPrice($avgImpact) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tank Details -->
                            <h4 class="text-lg font-medium text-gray-900 mb-3">Impact by Tank</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tank
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Stock Volume (Liters)
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Value Change
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Impact per Liter
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($impact['tanks'] as $tank): ?>
                                            <?php 
                                                $tankValueClass = $tank['value_change'] >= 0 ? 'text-green-600' : 'text-red-600';
                                                $impactPerLiter = $tank['stock_volume'] > 0 ? 
                                                    ($tank['value_change'] / $tank['stock_volume']) : 0;
                                                $impactPerLiterClass = $impactPerLiter >= 0 ? 'text-green-600' : 'text-red-600';
                                            ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-medium text-gray-900">
                                                        <?= htmlspecialchars($tank['tank_name']) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                                    <?= number_format($tank['stock_volume'], 2) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-medium <?= $tankValueClass ?>">
                                                        <?= $tank['value_change'] >= 0 ? '+' : '' ?><?= formatPrice($tank['value_change']) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-medium <?= $impactPerLiterClass ?>">
                                                        <?= $impactPerLiter >= 0 ? '+' : '' ?><?= formatPrice($impactPerLiter) ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Price Change Impact Explanation -->
<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="bg-blue-600 px-4 py-3">
        <h2 class="text-white text-lg font-semibold">Understanding Price Change Impact</h2>
    </div>
    
    <div class="p-4">
        <div class="space-y-4">
            <p class="text-gray-700">
                When fuel prices change, the value of your existing inventory is affected. This is important to understand for financial reporting and inventory valuation.
            </p>
            
            <h3 class="text-lg font-medium text-gray-900">Example Scenarios:</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="text-green-800 font-medium mb-2">Price Increase</h4>
                    <p class="text-gray-700 mb-2">
                        When prices increase, the value of your existing inventory increases.
                    </p>
                    <div class="bg-white p-3 rounded border border-green-200">
                        <p class="text-sm text-gray-600 mb-1">Example:</p>
                        <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                            <li>Current tank: 10,000 liters of Petrol 92</li>
                            <li>Price change: <?= CURRENCY_SYMBOL ?>100 → <?= CURRENCY_SYMBOL ?>110 per liter</li>
                            <li>Value change: +<?= CURRENCY_SYMBOL ?>100,000</li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="text-red-800 font-medium mb-2">Price Decrease</h4>
                    <p class="text-gray-700 mb-2">
                        When prices decrease, the value of your existing inventory decreases.
                    </p>
                    <div class="bg-white p-3 rounded border border-red-200">
                        <p class="text-sm text-gray-600 mb-1">Example:</p>
                        <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                            <li>Current tank: 5,000 liters of Diesel</li>
                            <li>Price change: <?= CURRENCY_SYMBOL ?>120 → <?= CURRENCY_SYMBOL ?>110 per liter</li>
                            <li>Value change: -<?= CURRENCY_SYMBOL ?>50,000</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <p class="text-gray-700">
                The system automatically calculates these impacts whenever a price change becomes active. This data can be used for:
            </p>
            
            <ul class="list-disc list-inside text-gray-700 space-y-1">
                <li>Financial reporting and reconciliation</li>
                <li>Inventory valuation</li>
                <li>Profit/loss analysis</li>
                <li>Budgeting and forecasting</li>
            </ul>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>