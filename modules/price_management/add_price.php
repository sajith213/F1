<?php
/**
 * Add New Price
 * 
 * This page allows adding new fuel prices
 */

// Set page title and include header
$page_title = "Add New Price";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Price Management</a> / Add New Price";

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

// Initialize variables
$errors = [];
$success = false;

// Get all fuel types for dropdown
$fuel_types = getAllFuelTypes();

// Get current active prices for reference
$current_prices = getCurrentFuelPrices();
$currentPriceByFuel = [];
foreach ($current_prices as $price) {
    $currentPriceByFuel[$price['fuel_type_id']] = [
        'purchase_price' => $price['purchase_price'],
        'selling_price' => $price['selling_price']
    ];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $fuel_type_id = isset($_POST['fuel_type_id']) ? intval($_POST['fuel_type_id']) : 0;
    $effective_date = isset($_POST['effective_date']) ? trim($_POST['effective_date']) : '';
    $purchase_price = isset($_POST['purchase_price']) ? filter_var($_POST['purchase_price'], FILTER_VALIDATE_FLOAT) : false;
    $selling_price = isset($_POST['selling_price']) ? filter_var($_POST['selling_price'], FILTER_VALIDATE_FLOAT) : false;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validation
    if ($fuel_type_id <= 0) {
        $errors[] = "Please select a valid fuel type.";
    }
    
    if (empty($effective_date)) {
        $errors[] = "Effective date is required.";
    } else {
        // Check if date is valid and not too far in the past
        $min_allowed_date = date('Y-m-d', strtotime('-6 day'));
        if ($effective_date < $min_allowed_date) {
            $errors[] = "Effective date cannot be more than 6 days in the past.";
        }
    }
    
    if ($purchase_price === false || $purchase_price <= 0) {
        $errors[] = "Please enter a valid purchase price.";
    }
    
    if ($selling_price === false || $selling_price <= 0) {
        $errors[] = "Please enter a valid selling price.";
    }
    
    if ($selling_price <= $purchase_price) {
        $errors[] = "Selling price must be greater than purchase price.";
    }
    
    // If no errors, save the new price
    if (empty($errors)) {
        $user_id = $_SESSION['user_id'];
        
        $new_price_id = addFuelPrice($fuel_type_id, $effective_date, $purchase_price, $selling_price, $user_id, $notes);
        
        if ($new_price_id) {
            $success = true;
        } else {
            $errors[] = "Failed to add new price. Please try again.";
        }
    }
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="bg-blue-600 px-4 py-3">
        <h2 class="text-white text-lg font-semibold">Add New Fuel Price</h2>
    </div>
    
    <div class="p-4">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <strong>Success!</strong> New price has been added successfully.
                <div class="mt-2">
                    <a href="index.php" class="text-green-700 underline">Return to Price Management</a> or 
                    <a href="javascript:location.reload();" class="text-green-700 underline">Add Another Price</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Please fix the following errors:</strong>
                <ul class="list-disc list-inside mt-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" id="priceForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Fuel Type -->
                <div>
                    <label for="fuel_type_id" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type <span class="text-red-600">*</span></label>
                    <select id="fuel_type_id" name="fuel_type_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Fuel Type</option>
                        <?php foreach ($fuel_types as $type): ?>
                            <option value="<?= $type['fuel_type_id'] ?>" <?= isset($_POST['fuel_type_id']) && $_POST['fuel_type_id'] == $type['fuel_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['fuel_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Effective Date -->
                <div>
                    <label for="effective_date" class="block text-sm font-medium text-gray-700 mb-1">Effective Date <span class="text-red-600">*</span></label>
                    <input type="date" id="effective_date" name="effective_date" value="<?= isset($_POST['effective_date']) ? $_POST['effective_date'] : date('Y-m-d') ?>" 
                           min="<?= date('Y-m-d', strtotime('-6 day')) ?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Purchase Price -->
                <div>
                    <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-1">Purchase Price <span class="text-red-600">*</span></label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?= CURRENCY_SYMBOL ?></span>
                        </div>
                        <input type="number" id="purchase_price" name="purchase_price" min="0.01" step="0.01" 
                               value="<?= isset($_POST['purchase_price']) ? $_POST['purchase_price'] : '' ?>"
                               class="block w-full pl-7 pr-12 border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="0.00" required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm" id="current-purchase-price"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Selling Price -->
                <div>
                    <label for="selling_price" class="block text-sm font-medium text-gray-700 mb-1">Selling Price <span class="text-red-600">*</span></label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?= CURRENCY_SYMBOL ?></span>
                        </div>
                        <input type="number" id="selling_price" name="selling_price" min="0.01" step="0.01" 
                               value="<?= isset($_POST['selling_price']) ? $_POST['selling_price'] : '' ?>"
                               class="block w-full pl-7 pr-12 border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="0.00" required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm" id="current-selling-price"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profit Margin Preview -->
            <div id="profit-preview" class="bg-gray-50 p-3 rounded-md hidden">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Profit Margin:</span>
                        <span id="profit-amount" class="ml-2 text-green-600 font-medium"></span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-700">Profit Percentage:</span>
                        <span id="profit-percentage" class="ml-2 text-green-600 font-medium"></span>
                    </div>
                </div>
            </div>
            
            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                <p class="mt-1 text-sm text-gray-500">Add any additional information about this price change.</p>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Price
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Current prices from PHP to JavaScript
    const currentPrices = <?= json_encode($currentPriceByFuel) ?>;
    
    // Calculate profit margin and percentage
    function calculateProfit() {
        const purchasePrice = parseFloat(document.getElementById('purchase_price').value) || 0;
        const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
        
        if (purchasePrice > 0 && sellingPrice > 0) {
            const profitMargin = sellingPrice - purchasePrice;
            const profitPercentage = (profitMargin / purchasePrice) * 100;
            
            document.getElementById('profit-amount').textContent = 
                '<?= CURRENCY_SYMBOL ?>' + profitMargin.toFixed(2);
            document.getElementById('profit-percentage').textContent = 
                profitPercentage.toFixed(2) + '%';
            
            document.getElementById('profit-preview').classList.remove('hidden');
        } else {
            document.getElementById('profit-preview').classList.add('hidden');
        }
    }
    
    // Update current price indicators when fuel type changes
    document.getElementById('fuel_type_id').addEventListener('change', function() {
        const fuelTypeId = this.value;
        const purchaseElement = document.getElementById('current-purchase-price');
        const sellingElement = document.getElementById('current-selling-price');
        
        if (fuelTypeId && currentPrices[fuelTypeId]) {
            const current = currentPrices[fuelTypeId];
            purchaseElement.textContent = 'Current: <?= CURRENCY_SYMBOL ?>' + current.purchase_price;
            sellingElement.textContent = 'Current: <?= CURRENCY_SYMBOL ?>' + current.selling_price;
            
            // Pre-fill with current prices if fields are empty
            if (!document.getElementById('purchase_price').value) {
                document.getElementById('purchase_price').value = current.purchase_price;
            }
            if (!document.getElementById('selling_price').value) {
                document.getElementById('selling_price').value = current.selling_price;
            }
            
            calculateProfit();
        } else {
            purchaseElement.textContent = '';
            sellingElement.textContent = '';
        }
    });
    
    // Calculate profit on input change
    document.getElementById('purchase_price').addEventListener('input', calculateProfit);
    document.getElementById('selling_price').addEventListener('input', calculateProfit);
    
    // Form validation
    document.getElementById('priceForm').addEventListener('submit', function(e) {
        const purchasePrice = parseFloat(document.getElementById('purchase_price').value) || 0;
        const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
        
        if (sellingPrice <= purchasePrice) {
            e.preventDefault();
            alert('Selling price must be greater than purchase price.');
        }
    });
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Trigger change event to populate current prices
        const event = new Event('change');
        document.getElementById('fuel_type_id').dispatchEvent(event);
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>