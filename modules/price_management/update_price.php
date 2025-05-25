<?php
/**
 * Update Price
 * 
 * This page allows updating planned fuel prices
 */

// Set page title and include header
$page_title = "Update Price";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Price Management</a> / Update Price";

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
$price_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get price data
$price = getPriceById($price_id);

// Redirect if price not found or not in planned status
if (!$price || $price['status'] !== 'planned') {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <strong>Error!</strong> The requested price change cannot be updated. Only planned prices can be modified.
          </div>";
    echo "<div class='mt-4'>
            <a href='index.php' class='inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500'>
                Return to Price Management
            </a>
          </div>";
    include '../../includes/footer.php';
    exit;
}

// Get current active price for reference
$currentPrice = null;
$current_prices = getCurrentFuelPrices();
foreach ($current_prices as $cp) {
    if ($cp['fuel_type_id'] == $price['fuel_type_id']) {
        $currentPrice = $cp;
        break;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $effective_date = isset($_POST['effective_date']) ? trim($_POST['effective_date']) : '';
    $purchase_price = isset($_POST['purchase_price']) ? filter_var($_POST['purchase_price'], FILTER_VALIDATE_FLOAT) : false;
    $selling_price = isset($_POST['selling_price']) ? filter_var($_POST['selling_price'], FILTER_VALIDATE_FLOAT) : false;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validation
    if (empty($effective_date)) {
        $errors[] = "Effective date is required.";
    } else {
        // Check if date is valid and not in the past
        $today = date('Y-m-d');
        if ($effective_date < $today) {
            // Allow yesterday's date for corrections but warn
            if ($effective_date < date('Y-m-d', strtotime('-1 day'))) {
                $errors[] = "Effective date cannot be in the past.";
            }
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
    
    // If no errors, update the price
    if (empty($errors)) {
        $result = updateFuelPrice($price_id, $effective_date, $purchase_price, $selling_price, $notes);
        
        if ($result) {
            $success = true;
            // Refresh price data
            $price = getPriceById($price_id);
        } else {
            $errors[] = "Failed to update price. Please try again.";
        }
    }
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="bg-indigo-600 px-4 py-3">
        <h2 class="text-white text-lg font-semibold">Update Planned Price Change</h2>
    </div>
    
    <div class="p-4">
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <strong>Success!</strong> Price has been updated successfully.
                <div class="mt-2">
                    <a href="index.php" class="text-green-700 underline">Return to Price Management</a>
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
        
        <!-- Price Information Card -->
        <div class="bg-blue-50 p-4 rounded-lg mb-6">
            <h3 class="text-blue-800 font-medium mb-2">Price Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-600">Fuel Type:</span> 
                    <span class="font-medium text-gray-900"><?= htmlspecialchars($price['fuel_name']) ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Status:</span> 
                    <span class="font-medium px-2 py-1 rounded-full bg-blue-100 text-blue-800">Planned</span>
                </div>
                <?php if ($currentPrice): ?>
                <div>
                    <span class="text-gray-600">Current Purchase Price:</span> 
                    <span class="font-medium text-gray-900"><?= formatPrice($currentPrice['purchase_price']) ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Current Selling Price:</span> 
                    <span class="font-medium text-gray-900"><?= formatPrice($currentPrice['selling_price']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <form method="post" id="priceForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Effective Date -->
                <div>
                    <label for="effective_date" class="block text-sm font-medium text-gray-700 mb-1">Effective Date <span class="text-red-600">*</span></label>
                    <input type="date" id="effective_date" name="effective_date" value="<?= $price['effective_date'] ?>" 
                           min="<?= date('Y-m-d', strtotime('-1 day')) ?>" 
                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Purchase Price -->
                <div>
                    <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-1">Purchase Price <span class="text-red-600">*</span></label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?= CURRENCY_SYMBOL ?></span>
                        </div>
                        <input type="number" id="purchase_price" name="purchase_price" min="0.01" step="0.01" 
                               value="<?= $price['purchase_price'] ?>"
                               class="block w-full pl-7 pr-12 border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
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
                               value="<?= $price['selling_price'] ?>"
                               class="block w-full pl-7 pr-12 border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>
                
                <!-- Profit Margin Preview -->
                <div id="profit-preview" class="bg-gray-50 p-3 rounded-md">
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
            </div>
            
            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($price['notes'] ?? '') ?></textarea>
                <p class="mt-1 text-sm text-gray-500">Add any additional information about this price change.</p>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Price
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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
        }
    }
    
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
        calculateProfit();
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>