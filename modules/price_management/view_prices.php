<?php
/**
 * View Price History
 * 
 * This page displays historical price changes with filtering options
 */

// Set page title and include header
$page_title = "Price History";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Price Management</a> / Price History";

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

// Get query parameters
$fuel_type_id = isset($_GET['fuel_type_id']) ? intval($_GET['fuel_type_id']) : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

// Get all fuel types for filter dropdown
$fuel_types = getAllFuelTypes();

// Get price history based on filters
$prices = getPriceHistory($fuel_type_id, $start_date, $end_date);

// Group prices by fuel type for better organization
$groupedPrices = [];
foreach ($prices as $price) {
    if ($status && $price['status'] !== $status) {
        continue;
    }
    
    if (!isset($groupedPrices[$price['fuel_type_id']])) {
        $groupedPrices[$price['fuel_type_id']] = [
            'fuel_name' => $price['fuel_name'],
            'prices' => []
        ];
    }
    
    $groupedPrices[$price['fuel_type_id']]['prices'][] = $price;
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
    <div class="bg-blue-600 px-4 py-3">
        <h2 class="text-white text-lg font-semibold">Filter Price History</h2>
    </div>
    
    <div class="p-4">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Fuel Type Filter -->
            <div>
                <label for="fuel_type_id" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                <select id="fuel_type_id" name="fuel_type_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Fuel Types</option>
                    <?php foreach ($fuel_types as $type): ?>
                        <option value="<?= $type['fuel_type_id'] ?>" <?= $fuel_type_id == $type['fuel_type_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['fuel_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Start Date Filter -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" 
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- End Date Filter -->
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" 
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="planned" <?= $status === 'planned' ? 'selected' : '' ?>>Planned</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
            
            <!-- Filter Actions -->
            <div class="md:col-span-4 flex justify-end space-x-3">
                <a href="view_prices.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Clear Filters
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($groupedPrices)): ?>
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="p-6 text-center text-gray-500">
            <p class="text-xl mb-2">No price history found</p>
            <p class="mb-4">Try adjusting your filters or add new prices.</p>
            <a href="add_price.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Add New Price
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($groupedPrices as $fuelTypeId => $fuelData): ?>
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <div class="bg-indigo-600 px-4 py-3">
                <h2 class="text-white text-lg font-semibold"><?= htmlspecialchars($fuelData['fuel_name']) ?> Price History</h2>
            </div>
            
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Effective Date
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
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Set By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $previousPrice = null;
                            foreach ($fuelData['prices'] as $index => $price): 
                                $priceChange = null;
                                $percentChange = null;
                                
                                if ($index < count($fuelData['prices']) - 1) {
                                    $nextPrice = $fuelData['prices'][$index + 1];
                                    $priceChange = $price['selling_price'] - $nextPrice['selling_price'];
                                    $percentChange = ($nextPrice['selling_price'] > 0) ? 
                                        ($priceChange / $nextPrice['selling_price'] * 100) : 0;
                                }
                                
                                $statusClass = 'bg-gray-100 text-gray-800';
                                if ($price['status'] === 'active') {
                                    $statusClass = 'bg-green-100 text-green-800';
                                } elseif ($price['status'] === 'planned') {
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                }
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">
                                            <?= date('M d, Y', strtotime($price['effective_date'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        <?= formatPrice($price['purchase_price']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">
                                            <?= formatPrice($price['selling_price']) ?>
                                            <?php if ($priceChange !== null): ?>
                                                <span class="text-xs ml-1 <?= $priceChange >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                                    (<?= $priceChange >= 0 ? '+' : '' ?><?= formatPrice($priceChange) ?> / 
                                                    <?= $percentChange >= 0 ? '+' : '' ?><?= formatPercentage($percentChange) ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-green-600">
                                            <?= formatPrice($price['profit_margin']) ?> 
                                            (<?= formatPercentage($price['profit_percentage']) ?>)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                            <?= ucfirst($price['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        <?= htmlspecialchars($price['set_by_name'] ?? 'Unknown') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($price['status'] === 'planned'): ?>
                                            <div class="flex space-x-2">
                                                <a href="update_price.php?id=<?= $price['price_id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?= $price['price_id'] ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add New Price Button -->
<div class="mt-4 text-center">
    <a href="add_price.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <i class="fas fa-plus mr-2"></i> Add New Price
    </a>
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
    
    // Date validation for filters
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        startDateInput.addEventListener('change', function() {
            if (endDateInput.value && this.value > endDateInput.value) {
                endDateInput.value = this.value;
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (startDateInput.value && this.value < startDateInput.value) {
                startDateInput.value = this.value;
            }
        });
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>