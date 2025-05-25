<?php
/**
 * POS Module - Sales Interface
 * * This file provides the main Point of Sale interface for creating sales
 */

// Set page title
$page_title = "Point of Sale";
$hide_breadcrumbs = true; // Hide breadcrumbs for this page

// Include header
include_once '../../includes/header.php';

// Include database connection if not already included in header
require_once '../../includes/db.php';

// Include functions
require_once 'functions.php';

// Include credit sales functions
require_once 'credit_sales_functions.php';

// Check if user has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'cashier'])) {
    echo "<p>Unauthorized access or session expired. Please log in.</p>";
    include_once '../../includes/footer.php';
    exit;
}

// Get credit customers
$creditCustomers = getCreditCustomers($conn);

// Default due date (30 days)
$defaultDueDate = getDefaultDueDate();

// Get all fuel nozzles
$fuelNozzles = [];
$stmt = $conn->prepare("
    SELECT n.nozzle_id, n.nozzle_number, n.fuel_type_id, n.status,
           p.pump_id, p.pump_name,
           f.fuel_name, fp.selling_price
    FROM pump_nozzles n
    JOIN pumps p ON n.pump_id = p.pump_id
    JOIN fuel_types f ON n.fuel_type_id = f.fuel_type_id
    LEFT JOIN (
        SELECT fp1.*
        FROM fuel_prices fp1
        LEFT JOIN fuel_prices fp2 ON fp1.fuel_type_id = fp2.fuel_type_id AND (
            fp1.effective_date < fp2.effective_date OR
            (fp1.effective_date = fp2.effective_date AND fp1.price_id < fp2.price_id)
        )
        WHERE fp2.price_id IS NULL AND fp1.status = 'active'
    ) fp ON f.fuel_type_id = fp.fuel_type_id
    WHERE n.status = 'active' AND p.status = 'active'
    ORDER BY p.pump_name, n.nozzle_number
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $fuelNozzles[] = $row;
    }
    $stmt->close();
}

// Get all active product categories
$productCategories = [];
$stmt = $conn->prepare("SELECT category_id, category_name FROM product_categories WHERE status = 'active' ORDER BY category_name");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $productCategories[] = $row;
    }
    $stmt->close();
} else {
     error_log("Error preparing product categories query: " . $conn->error);
}

// Get all staff members for dropdown
$staffMembers = [];
$stmt = $conn->prepare("
    SELECT staff_id, first_name, last_name
    FROM staff
    WHERE status = 'active'
    ORDER BY first_name, last_name
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $staffMembers[] = $row;
    }
    $stmt->close();
} else {
     error_log("Error preparing staff members query: " . $conn->error);
}

// Get next invoice number
$invoicePrefix = 'INV-';
$currentDate = date('Ymd');
$nextInvoice = $invoicePrefix . $currentDate . '-0001';

$stmt = $conn->prepare("
    SELECT invoice_number FROM sales
    WHERE invoice_number LIKE ?
    ORDER BY invoice_number DESC LIMIT 1
");
if ($stmt) {
    $likeParam = $invoicePrefix . $currentDate . '%';
    $stmt->bind_param("s", $likeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Extract last number and increment
        $lastInvoice = $row['invoice_number'];
        $lastNumber = (int)substr($lastInvoice, -4);
        $nextNumber = $lastNumber + 1;
        $nextInvoice = $invoicePrefix . $currentDate . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    $stmt->close();
} else {
     error_log("Error preparing invoice number query: " . $conn->error);
}

// Get currency symbol from settings
$currency_symbol = 'LKR'; // Default
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currency_symbol = !empty($row['setting_value']) ? $row['setting_value'] : 'LKR';
    }
    $stmt->close();
} else {
     error_log("Error preparing currency symbol query: " . $conn->error);
}

// Get VAT percentage from settings
$vat_percentage = 18.0; // Default
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'vat_percentage'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $vat_percentage = is_numeric($row['setting_value']) ? (float)$row['setting_value'] : 18.0;
    }
    $stmt->close();
} else {
     error_log("Error preparing VAT percentage query: " . $conn->error);
}

// Extra CSS for point of sale screen
$extra_css = '
<style>
    #pos-container {
        height: calc(100vh - 10rem);
    }

    #products-panel {
        height: 100%;
        overflow-y: auto;
    }

    #cart-panel {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    #cart-items {
        flex-grow: 1;
        overflow-y: auto;
        min-height: 100px;
    }

    #cart-summary {
        flex-shrink: 0;
    }

    .product-card {
        transition: all 0.2s ease;
    }

    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type=number] {
        -moz-appearance: textfield;
    }

    /* Highlight primary input */
    .primary-input {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
</style>';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div id="pos-container" class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <div id="products-panel" class="lg:col-span-2 bg-white rounded-lg shadow p-4">

            <div class="mb-4 flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" id="product-search" placeholder="Search products by name or code..."
                               class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <select id="category-filter" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <?php foreach ($productCategories as $category): ?>
                        <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button id="barcode-scan-btn" title="Scan Barcode (Functionality not implemented)" class="bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed focus:outline-none">
                        <i class="fas fa-barcode mr-1"></i>
                        Scan
                    </button>
                </div>
            </div>

            <div id="pos-tabs" class="mb-4 border-b border-gray-200">
                <div class="flex">
                    <button type="button" class="tab-button px-4 py-2 font-medium text-sm sm:text-base text-blue-600 border-b-2 border-blue-600" data-tab="products">Products</button>
                    <button type="button" class="tab-button px-4 py-2 font-medium text-sm sm:text-base text-gray-500 hover:text-gray-700" data-tab="fuel">Fuel</button>
                </div>
            </div>

            <div id="tab-products" class="tab-panel">
                <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
                    <div class="text-center text-gray-500 col-span-full py-12">
                        <div class="animate-spin inline-block w-8 h-8 border-4 border-gray-300 border-t-blue-600 rounded-full mb-4"></div>
                        <p>Loading products...</p>
                    </div>
                </div>
            </div>

            <div id="tab-fuel" class="tab-panel hidden">
                <?php if (empty($fuelNozzles)): ?>
                <div class="text-center text-gray-500 py-12">
                    <i class="fas fa-exclamation-circle text-yellow-500 text-4xl mb-3"></i>
                    <p>No active fuel nozzles found. Please configure pumps and nozzles first.</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($fuelNozzles as $nozzle): ?>
                    <div class="fuel-card border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer"
                         data-nozzle-id="<?= $nozzle['nozzle_id'] ?>"
                         data-fuel-type="<?= htmlspecialchars($nozzle['fuel_name']) ?>"
                         data-pump-name="<?= htmlspecialchars($nozzle['pump_name']) ?>"
                         data-nozzle-number="<?= $nozzle['nozzle_number'] ?>"
                         data-price="<?= $nozzle['selling_price'] ?? 0 ?>">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($nozzle['fuel_name']) ?></h3>
                                <p class="text-sm text-gray-500">
                                    <?= htmlspecialchars($nozzle['pump_name']) ?> - Nozzle <?= $nozzle['nozzle_number'] ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded">
                                Fuel
                            </div>
                        </div>

                        <div class="mt-2">
                            <div class="text-xl font-bold text-green-600">
                                <?= $currency_symbol ?> <?= number_format($nozzle['selling_price'] ?? 0, 2) ?>
                            </div>
                        </div>

                        <div class="mt-3 text-blue-600 text-sm font-medium">
                            <i class="fas fa-plus-circle"></i> Add to Sale
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="cart-panel" class="bg-white rounded-lg shadow flex flex-col">
            <div class="p-4 border-b border-gray-200 flex-shrink-0">
                <h2 class="text-lg font-semibold text-gray-800 mb-1">Current Sale</h2>
                <div class="text-xs text-gray-500">
                    Invoice: <span id="invoice-number" class="font-medium"><?= htmlspecialchars($nextInvoice) ?></span>
                    <span class="mx-1">|</span>
                    <span id="current-date"><?= date('M d, Y') ?></span>
                </div>
            </div>

            <div id="cart-items" class="p-4 divide-y divide-gray-200 flex-grow overflow-y-auto">
                <div id="empty-cart-message" class="pt-10 text-center text-gray-400">
                    <i class="fas fa-shopping-cart text-3xl mb-2"></i>
                    <p class="text-sm">Cart is empty</p>
                </div>
            </div>

            <div id="cart-summary" class="p-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                <div class="mb-4 space-y-1 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Subtotal:</span>
                        <span id="subtotal" class="font-medium"><?= $currency_symbol ?> 0.00</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">VAT (<?= number_format($vat_percentage, 1) ?>%):</span>
                        <span id="tax" class="font-medium"><?= $currency_symbol ?> 0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-base font-bold text-gray-800 mt-1 pt-1 border-t border-gray-200">
                        <span>Total:</span>
                        <span id="total"><?= $currency_symbol ?> 0.00</span>
                    </div>
                </div>

                 <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Sale Type</label>
                    <div class="flex space-x-4">
                        <div class="flex items-center">
                            <input type="radio" id="sale-type-regular" name="sale-type" value="regular" checked class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                            <label for="sale-type-regular" class="ml-2 block text-sm text-gray-700">Regular</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="sale-type-credit" name="sale-type" value="credit" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                            <label for="sale-type-credit" class="ml-2 block text-sm text-gray-700">Credit</label>
                        </div>
                    </div>
                </div>

                <div id="credit-customer-section" class="mb-3 bg-blue-50 p-3 rounded border border-blue-200 hidden">
                    <div class="mb-2">
                        <label for="credit-customer" class="block text-xs font-medium text-gray-700 mb-1">Credit Customer</label>
                        <select id="credit-customer" class="block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                            <option value="">Select Customer</option>
                            <?php foreach ($creditCustomers as $customer): ?>
                                <?php
                                $availableCredit = $customer['credit_limit'] - $customer['current_balance'];
                                $disabled = $availableCredit <= 0 ? 'disabled' : '';
                                $creditInfo = formatCreditAmount($availableCredit, $currency_symbol) . ' avail.';
                                ?>
                                <option value="<?= $customer['customer_id'] ?>" data-balance="<?= $customer['current_balance'] ?>"
                                        data-limit="<?= $customer['credit_limit'] ?>" <?= $disabled ?>>
                                    <?= htmlspecialchars($customer['customer_name']) ?> (<?= $creditInfo ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="credit-due-date" class="block text-xs font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" id="credit-due-date" value="<?= $defaultDueDate ?>" min="<?= date('Y-m-d') ?>"
                               class="block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                    </div>
                    <div id="credit-info" class="text-xs border rounded-md p-2 bg-white">
                        <div class="flex justify-between items-center"><span>Limit:</span> <span id="credit-limit" class="font-medium"><?= $currency_symbol ?>0.00</span></div>
                        <div class="flex justify-between items-center"><span>Balance:</span> <span id="credit-balance" class="font-medium"><?= $currency_symbol ?>0.00</span></div>
                        <div class="flex justify-between items-center"><span>Available:</span> <span id="credit-available" class="font-medium"><?= $currency_symbol ?>0.00</span></div>
                        <div class="flex justify-between items-center mt-1 pt-1 border-t"><span>New Balance:</span> <span id="credit-new-balance" class="font-medium"><?= $currency_symbol ?>0.00</span></div>
                    </div>
                 </div>

                <div class="mb-3">
                    <label for="staff-id" class="block text-xs font-medium text-gray-700 mb-1">Staff Member</label>
                    <select id="staff-id" class="block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                        <option value="">Select Staff</option> <?php foreach ($staffMembers as $staff): ?>
                        <option value="<?= $staff['staff_id'] ?>"><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="payment-method" class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                    <select id="payment-method" class="block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="mobile_payment">Mobile Payment</option>
                        </select>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Customer (Optional)</label>
                     <input type="text" id="customer-name" placeholder="Name" class="mb-1 block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                    <input type="text" id="customer-phone" placeholder="Phone" class="block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-xs">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button id="cancel-sale-btn" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </button>
                    <button id="complete-sale-btn" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Complete & Print </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div id="product-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="p-5">
            <div class="flex justify-between items-start mb-3">
                <h3 id="modal-product-name" class="text-base font-semibold text-gray-900">Product Name</h3>
                <button id="close-modal" type="button" class="text-gray-400 hover:text-gray-600 p-1 -m-1">
                    <i class="fas fa-times"></i> <span class="sr-only">Close</span>
                </button>
            </div>

            <div id="modal-product-details" class="text-xs text-gray-500 mb-3">
                Price: <span id="modal-product-price"></span> | Available: <span id="modal-product-stock"></span>
            </div>

            <div class="mb-4">
                <label for="product-quantity" class="block text-xs font-medium text-gray-700 mb-1">Quantity</label>
                <div class="flex items-center">
                    <button id="decrease-quantity" type="button" class="bg-gray-200 text-gray-700 hover:bg-gray-300 h-8 w-8 rounded-l-md flex items-center justify-center">
                        <i class="fas fa-minus text-xs"></i>
                    </button>
                    <input type="number" id="product-quantity" min="1" value="1"
                           class="h-8 text-center w-12 border-t border-b border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-300 text-sm">
                    <button id="increase-quantity" type="button" class="bg-gray-200 text-gray-700 hover:bg-gray-300 h-8 w-8 rounded-r-md flex items-center justify-center">
                        <i class="fas fa-plus text-xs"></i>
                    </button>
                </div>
            </div>

            <button id="add-to-cart" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm font-medium">
                Add to Cart
            </button>
        </div>
    </div>
</div>

<!-- Modified Fuel Modal - Amount First -->
<div id="fuel-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
     <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="p-5">
            <div class="flex justify-between items-start mb-3">
                <h3 id="modal-fuel-name" class="text-base font-semibold text-gray-900">Fuel Type</h3>
                 <button id="close-fuel-modal" type="button" class="text-gray-400 hover:text-gray-600 p-1 -m-1">
                    <i class="fas fa-times"></i> <span class="sr-only">Close</span>
                </button>
            </div>

             <div id="modal-fuel-details" class="text-xs text-gray-500 mb-3">
                Pump: <span id="modal-pump-name"></span> | Price: <span id="modal-fuel-price"></span>/L
            </div>

            <!-- Modified layout - Amount first, then Liters -->
            <div class="mb-4 space-y-3">
                <!-- Primary Input: Amount -->
                <div>
                    <label for="fuel-amount" class="block text-sm font-medium text-gray-700 mb-1">
                        Sale Amount (<?= $currency_symbol ?>) <span class="text-blue-600">*</span>
                    </label>
                    <input type="number" id="fuel-amount" min="0.01" step="0.01" placeholder="Enter amount"
                           class="primary-input block w-full border border-blue-400 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-base font-medium">
                    <p class="text-xs text-gray-500 mt-1">Enter the sale amount first</p>
                </div>
                
                <!-- Secondary Input: Liters (Auto-calculated) -->
                <div>
                    <label for="fuel-liters" class="block text-sm font-medium text-gray-600 mb-1">
                        Liters (Auto-calculated)
                    </label>
                    <input type="number" id="fuel-liters" min="0.0001" step="0.0001" placeholder="0.0000" readonly
                           class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-50 text-gray-700 text-base">
                    <p class="text-xs text-gray-500 mt-1">Calculated to 4 decimal places</p>
                </div>
            </div>

            <button id="add-fuel-to-cart" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm font-medium">
                Add Fuel to Cart
            </button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Sale Completed!</h3>
            <p class="text-sm text-gray-500 mb-4">Invoice: <strong id="success-invoice"></strong></p>

            <div id="credit-success-info" class="hidden">
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-left border border-blue-200">
                    <p class="font-medium text-blue-800 mb-1">Credit Sale Details</p>
                    <p>Customer: <span id="success-credit-customer" class="font-medium"></span></p>
                    <p>Due Date: <span id="success-credit-due-date" class="font-medium"></span></p>
                    <p>New Balance: <span id="success-credit-balance" class="font-medium"></span></p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-3">
                <a href="#" id="new-sale" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Start New Sale
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const productsGrid = document.getElementById('products-grid');
    const productSearch = document.getElementById('product-search');
    const categoryFilter = document.getElementById('category-filter');
    const cartItems = document.getElementById('cart-items');
    const emptyCartMessage = document.getElementById('empty-cart-message');
    const subtotalEl = document.getElementById('subtotal');
    const taxEl = document.getElementById('tax');
    const totalEl = document.getElementById('total');
    const staffSelect = document.getElementById('staff-id');
    const paymentMethodSelect = document.getElementById('payment-method');
    const customerNameInput = document.getElementById('customer-name');
    const customerPhoneInput = document.getElementById('customer-phone');
    const cancelSaleBtn = document.getElementById('cancel-sale-btn');
    const completeSaleBtn = document.getElementById('complete-sale-btn');

    // Credit sale elements
    const saleTypeRegular = document.getElementById('sale-type-regular');
    const saleTypeCredit = document.getElementById('sale-type-credit');
    const creditCustomerSection = document.getElementById('credit-customer-section');
    const creditCustomerSelect = document.getElementById('credit-customer');
    const creditDueDateInput = document.getElementById('credit-due-date');
    const creditLimitSpan = document.getElementById('credit-limit');
    const creditBalanceSpan = document.getElementById('credit-balance');
    const creditAvailableSpan = document.getElementById('credit-available');
    const creditNewBalanceSpan = document.getElementById('credit-new-balance');
    const creditSuccessInfo = document.getElementById('credit-success-info');
    const successCreditCustomer = document.getElementById('success-credit-customer');
    const successCreditDueDate = document.getElementById('success-credit-due-date');
    const successCreditBalance = document.getElementById('success-credit-balance');

    // Modal elements (Product)
    const productModal = document.getElementById('product-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalProductPrice = document.getElementById('modal-product-price');
    const modalProductStock = document.getElementById('modal-product-stock');
    const productQuantity = document.getElementById('product-quantity');
    const decreaseQuantityBtn = document.getElementById('decrease-quantity');
    const increaseQuantityBtn = document.getElementById('increase-quantity');
    const closeModalBtn = document.getElementById('close-modal');
    const addToCartBtn = document.getElementById('add-to-cart');

    // Modal elements (Fuel) - Updated for amount-first approach
    const fuelModal = document.getElementById('fuel-modal');
    const modalFuelName = document.getElementById('modal-fuel-name');
    const modalPumpName = document.getElementById('modal-pump-name');
    const modalFuelPrice = document.getElementById('modal-fuel-price');
    const fuelAmountInput = document.getElementById('fuel-amount'); // Now primary
    const fuelLitersInput = document.getElementById('fuel-liters'); // Now secondary
    const closeFuelModalBtn = document.getElementById('close-fuel-modal');
    const addFuelToCartBtn = document.getElementById('add-fuel-to-cart');

    // Modal elements (Success)
    const successModal = document.getElementById('success-modal');
    const successInvoice = document.getElementById('success-invoice');
    const newSaleBtn = document.getElementById('new-sale');

    // Cart state
    let cart = [];
    let selectedProduct = null;
    let selectedNozzle = null;

    // Config from PHP
    const taxRate = <?= $vat_percentage / 100 ?>;
    const currencySymbol = '<?= $currency_symbol ?>';

    // --- Initialization ---
    fetchProducts();
    updateCart();

    // --- Event Listeners ---
    productSearch.addEventListener('input', debounce(fetchProducts, 300));
    categoryFilter.addEventListener('change', fetchProducts);

    saleTypeRegular.addEventListener('change', toggleCreditSection);
    saleTypeCredit.addEventListener('change', toggleCreditSection);
    creditCustomerSelect.addEventListener('change', updateCreditInfo);

    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanels = document.querySelectorAll('.tab-panel');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTab = button.dataset.tab;

            tabButtons.forEach(btn => {
                if (btn.dataset.tab === targetTab) {
                    btn.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
                    btn.classList.remove('text-gray-500', 'hover:text-gray-700');
                } else {
                    btn.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
                    btn.classList.add('text-gray-500', 'hover:text-gray-700');
                }
            });

            tabPanels.forEach(panel => {
                if (panel.id === `tab-${targetTab}`) {
                    panel.classList.remove('hidden');
                } else {
                    panel.classList.add('hidden');
                }
            });
        });
    });

    // Modal listeners
    closeModalBtn?.addEventListener('click', () => productModal.classList.add('hidden'));
    addToCartBtn?.addEventListener('click', addProductToCart);
    decreaseQuantityBtn?.addEventListener('click', () => changeProductQuantity(-1));
    increaseQuantityBtn?.addEventListener('click', () => changeProductQuantity(1));
    productQuantity?.addEventListener('input', handleQuantityInput);

    closeFuelModalBtn?.addEventListener('click', () => fuelModal.classList.add('hidden'));
    addFuelToCartBtn?.addEventListener('click', addFuelToCart);
    
    // Updated fuel input listeners - amount drives calculation
    fuelAmountInput?.addEventListener('input', () => updateFuelCalculation('amount'));
    // Removed liters input listener since it's now readonly

    // Main action listeners
    cancelSaleBtn.addEventListener('click', handleCancelSale);
    completeSaleBtn.addEventListener('click', handleCompleteSaleAndPrint);

    // Success modal listener
    newSaleBtn.addEventListener('click', handleNewSale);

    // Delegate fuel card clicks
    document.getElementById('tab-fuel').addEventListener('click', function(event) {
        const fuelCard = event.target.closest('.fuel-card');
        if (fuelCard) {
            selectFuel(fuelCard);
        }
    });

    // Delegate product card clicks
    productsGrid.addEventListener('click', function(event) {
        const productCard = event.target.closest('.product-card');
        if (productCard && !productCard.classList.contains('cursor-not-allowed')) {
             const productData = {
                 product_id: productCard.dataset.productId,
                 product_name: productCard.dataset.productName,
                 selling_price: productCard.dataset.productPrice,
                 current_stock: parseInt(productCard.dataset.productStock, 10)
             };
            selectProduct(productData);
        }
    });

    // Delegate cart item removal
    cartItems.addEventListener('click', function(event) {
        const removeButton = event.target.closest('button[data-index]');
        if(removeButton) {
            const indexToRemove = parseInt(removeButton.dataset.index, 10);
            removeCartItem(indexToRemove);
        }
    });

    // --- Core Functions ---

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Fetch products from API
    function fetchProducts() {
        const searchTerm = productSearch.value;
        const categoryId = categoryFilter.value;
        productsGrid.innerHTML = getLoadingIndicatorHTML();

        fetch(`../../api/pos_api.php?action=getProducts&search=${encodeURIComponent(searchTerm)}&category=${categoryId}`)
            .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok.'))
            .then(data => {
                if (data.status === 'success') {
                    renderProducts(data.products);
                } else {
                    productsGrid.innerHTML = getErrorHTML(`Error: ${data.message || 'Could not load products.'}`);
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                productsGrid.innerHTML = getErrorHTML('Failed to load products. Check connection or API.');
            });
    }

    // Render products grid
    function renderProducts(products) {
        if (!products || products.length === 0) {
            productsGrid.innerHTML = '<div class="text-center text-gray-500 col-span-full py-12"><i class="fas fa-box-open text-4xl mb-3"></i><p>No products found</p></div>';
            return;
        }

        productsGrid.innerHTML = '';
        products.forEach(product => {
            const productCard = document.createElement('div');
            const price = parseFloat(product.selling_price);
            const stock = parseInt(product.current_stock, 10);
            const reorderLevel = parseInt(product.reorder_level, 10);
            const isOutOfStock = stock <= 0;
            const isLowStock = !isOutOfStock && stock <= reorderLevel;

            let stockStatus = 'In Stock';
            let stockClass = 'bg-green-100 text-green-800';
            if (isOutOfStock) {
                stockStatus = 'Out of Stock';
                stockClass = 'bg-red-100 text-red-800';
            } else if (isLowStock) {
                stockStatus = 'Low Stock';
                stockClass = 'bg-yellow-100 text-yellow-800';
            }

            productCard.className = `product-card border border-gray-200 rounded-lg p-3 cursor-pointer transition duration-150 ease-in-out ${isOutOfStock ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-md'}`;
            productCard.dataset.productId = product.product_id;
            productCard.dataset.productName = product.product_name;
            productCard.dataset.productPrice = price;
            productCard.dataset.productStock = stock;

            productCard.innerHTML = `
                <h3 class="font-medium text-sm text-gray-800 line-clamp-2 mb-1">${product.product_name}</h3>
                <div class="flex justify-between items-center mt-1">
                    <div class="text-base font-bold text-green-600">${currencySymbol}${price.toFixed(2)}</div>
                    <div class="${stockClass} text-xs font-semibold px-1.5 py-0.5 rounded">${stockStatus}</div>
                </div>
            `;
            productsGrid.appendChild(productCard);
        });
    }

    // Select Product -> Show Modal
    function selectProduct(product) {
        if (!product || product.current_stock <= 0) return;
        selectedProduct = product;

        modalProductName.textContent = product.product_name;
        modalProductPrice.textContent = `${currencySymbol}${parseFloat(product.selling_price).toFixed(2)}`;
        modalProductStock.textContent = `${product.current_stock} available`;
        productQuantity.value = 1;
        productQuantity.max = product.current_stock;
        productModal.classList.remove('hidden');
        productQuantity.focus();
    }

    // Select Fuel -> Show Modal (Updated for amount-first approach)
    function selectFuel(nozzleElement) {
        selectedNozzle = nozzleElement;
        const price = parseFloat(selectedNozzle.dataset.price);

        modalFuelName.textContent = selectedNozzle.dataset.fuelType;
        modalPumpName.textContent = `${selectedNozzle.dataset.pumpName} - Nozzle ${selectedNozzle.dataset.nozzleNumber}`;
        modalFuelPrice.textContent = `${currencySymbol}${price.toFixed(2)}`;
        
        // Clear inputs and focus on amount field
        fuelAmountInput.value = '';
        fuelLitersInput.value = '';
        fuelModal.classList.remove('hidden');
        fuelAmountInput.focus(); // Focus on amount input first
    }

    // Add Product from Modal to Cart
    function addProductToCart() {
        if (!selectedProduct) return;
        const quantity = parseInt(productQuantity.value, 10);

        if (isNaN(quantity) || quantity < 1) {
            alert('Invalid quantity.'); return;
        }
        if (quantity > selectedProduct.current_stock) {
            alert('Quantity exceeds available stock.'); return;
        }

        const existingItemIndex = cart.findIndex(item => item.type === 'product' && item.id === selectedProduct.product_id);
        const price = parseFloat(selectedProduct.selling_price);

        if (existingItemIndex !== -1) {
            cart[existingItemIndex].quantity += quantity;
            if(cart[existingItemIndex].quantity > selectedProduct.current_stock) {
                cart[existingItemIndex].quantity = selectedProduct.current_stock;
                alert('Total quantity cannot exceed stock. Set to max available.');
            }
            cart[existingItemIndex].total = price * cart[existingItemIndex].quantity;
        } else {
            cart.push({
                type: 'product',
                id: selectedProduct.product_id,
                name: selectedProduct.product_name,
                price: price,
                quantity: quantity,
                total: price * quantity
            });
        }
        productModal.classList.add('hidden');
        updateCart();
    }

    // Add Fuel from Modal to Cart (Updated for amount-first validation)
    function addFuelToCart() {
        if (!selectedNozzle) return;
        
        const amount = parseFloat(fuelAmountInput.value);
        const fuelPrice = parseFloat(selectedNozzle.dataset.price);

        if (isNaN(amount) || amount <= 0) {
             alert('Please enter a valid sale amount.'); 
             fuelAmountInput.focus();
             return;
        }

        // Calculate liters from amount (amount / price per liter)
        const liters = amount / fuelPrice;

        cart.push({
            type: 'fuel',
            id: selectedNozzle.dataset.nozzleId,
            name: selectedNozzle.dataset.fuelType,
            price: fuelPrice,
            quantity: liters, // Store calculated liters
            total: amount, // Use entered amount
            pumpName: selectedNozzle.dataset.pumpName,
            nozzleNumber: selectedNozzle.dataset.nozzleNumber
        });
        
        fuelModal.classList.add('hidden');
        updateCart();
    }

    // Update Cart UI and Totals
    function updateCart() {
        if (cart.length === 0) {
            emptyCartMessage.classList.remove('hidden');
            cartItems.innerHTML = '';
        } else {
            emptyCartMessage.classList.add('hidden');
            cartItems.innerHTML = '';
            cart.forEach((item, index) => {
                cartItems.appendChild(createCartItemElement(item, index));
            });
        }
        calculateTotals();
        if (saleTypeCredit.checked) {
            updateCreditInfo();
        }
        completeSaleBtn.disabled = cart.length === 0;
    }

    // Create HTML for a single cart item (Updated to show 4 decimal liters)
    function createCartItemElement(item, index) {
        const itemElement = document.createElement('div');
        itemElement.className = 'py-2 flex justify-between items-start';
        let itemDetailsHTML = '';
        if (item.type === 'product') {
             itemDetailsHTML = `<p class="text-xs text-gray-500">${item.quantity} x ${currencySymbol}${item.price.toFixed(2)}</p>`;
        } else { // Fuel - show 4 decimal places for liters
            itemDetailsHTML = `<p class="text-xs text-gray-500">${item.quantity.toFixed(4)} L @ ${currencySymbol}${item.price.toFixed(2)}/L</p>
                               <p class="text-xxs text-gray-400">${item.pumpName}-N${item.nozzleNumber}</p>`;
        }

        itemElement.innerHTML = `
            <div class="flex-grow pr-2">
                <h4 class="text-sm font-medium text-gray-800 leading-tight">${item.name}</h4>
                ${itemDetailsHTML}
            </div>
            <div class="flex items-center flex-shrink-0">
                <span class="text-sm font-medium w-16 text-right">${currencySymbol}${item.total.toFixed(2)}</span>
                <button type="button" class="ml-2 text-red-500 hover:text-red-700 p-1" data-index="${index}" title="Remove Item">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        `;
        return itemElement;
    }

    // Remove item from cart array and update UI
    function removeCartItem(index) {
        if (index >= 0 && index < cart.length) {
            cart.splice(index, 1);
            updateCart();
        }
    }

    // Calculate and display totals
    function calculateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
        const tax = subtotal * taxRate;
        const total = subtotal + tax;

        subtotalEl.textContent = `${currencySymbol}${subtotal.toFixed(2)}`;
        taxEl.textContent = `${currencySymbol}${tax.toFixed(2)}`;
        totalEl.textContent = `${currencySymbol}${total.toFixed(2)}`;
    }

    // Handle quantity changes in product modal
    function changeProductQuantity(amount) {
        let currentVal = parseInt(productQuantity.value, 10);
        let maxStock = parseInt(productQuantity.max, 10);
        if (isNaN(currentVal)) currentVal = 0;
        let newVal = currentVal + amount;
        if (newVal < 1) newVal = 1;
        if (newVal > maxStock) newVal = maxStock;
        productQuantity.value = newVal;
    }

    function handleQuantityInput() {
         let currentVal = parseInt(productQuantity.value, 10);
         let maxStock = parseInt(productQuantity.max, 10);
         if (isNaN(currentVal) || currentVal < 1) {
             productQuantity.value = 1;
         } else if (currentVal > maxStock) {
             productQuantity.value = maxStock;
             alert('Quantity cannot exceed stock.');
         }
    }

    // Updated fuel calculation function - amount drives liters calculation
    function updateFuelCalculation(source) {
        if (!selectedNozzle) return;
        const fuelPrice = parseFloat(selectedNozzle.dataset.price);
        if (isNaN(fuelPrice) || fuelPrice <= 0) return;

        if (source === 'amount') {
            const amount = parseFloat(fuelAmountInput.value);
            if (!isNaN(amount) && amount > 0) {
                // Calculate liters to 4 decimal places
                const liters = amount / fuelPrice;
                fuelLitersInput.value = liters.toFixed(4);
            } else {
                fuelLitersInput.value = '';
            }
        }
        // Note: We removed the 'liters' source since that input is now readonly
    }

    // Toggle Credit Sale Section
    function toggleCreditSection() {
        if (saleTypeCredit.checked) {
            creditCustomerSection.classList.remove('hidden');
            paymentMethodSelect.value = 'credit';
            paymentMethodSelect.disabled = true;
            updateCreditInfo();
        } else {
            creditCustomerSection.classList.add('hidden');
             if (paymentMethodSelect.value === 'credit') {
                 paymentMethodSelect.value = 'cash';
             }
            paymentMethodSelect.disabled = false;
            resetCreditInfo();
        }
    }

    // Update Credit Info Display
    function updateCreditInfo() {
        const selectedOption = creditCustomerSelect.options[creditCustomerSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            resetCreditInfo(); return;
        }

        const balance = parseFloat(selectedOption.dataset.balance || 0);
        const limit = parseFloat(selectedOption.dataset.limit || 0);
        const available = limit - balance;
        const currentTotal = parseFloat(totalEl.textContent.replace(currencySymbol, '')) || 0;
        const newBalance = balance + currentTotal;

        creditLimitSpan.textContent = `${currencySymbol}${limit.toFixed(2)}`;
        creditBalanceSpan.textContent = `${currencySymbol}${balance.toFixed(2)}`;
        creditAvailableSpan.textContent = `${currencySymbol}${available.toFixed(2)}`;
        creditNewBalanceSpan.textContent = `${currencySymbol}${newBalance.toFixed(2)}`;

        creditNewBalanceSpan.classList.toggle('text-red-600', newBalance > limit);
        creditAvailableSpan.classList.toggle('text-red-600', available < 0);
    }

    // Reset Credit Info Display
    function resetCreditInfo() {
        creditLimitSpan.textContent = `${currencySymbol} --`;
        creditBalanceSpan.textContent = `${currencySymbol} --`;
        creditAvailableSpan.textContent = `${currencySymbol} --`;
        creditNewBalanceSpan.textContent = `${currencySymbol} --`;
         creditNewBalanceSpan.classList.remove('text-red-600');
         creditAvailableSpan.classList.remove('text-red-600');
    }

    // Handle Cancel Sale Button
    function handleCancelSale() {
        if (cart.length > 0) {
            if (confirm('Are you sure you want to cancel this sale? All items will be removed.')) {
                resetSale();
            }
        } else {
            resetSale();
        }
    }

    // Handle Complete Sale and Print
    function handleCompleteSaleAndPrint() {
        if (cart.length === 0) {
            alert('Cart is empty.'); return;
        }

        // Gather Data
        const staffId = staffSelect.value;
        const isCredit = saleTypeCredit.checked;
        let paymentMethod = paymentMethodSelect.value;
        let customerName = customerNameInput.value.trim();
        let customerPhone = customerPhoneInput.value.trim();
        let creditCustomerId = null;
        let dueDate = null;
        let canProceed = true;

        // Validate Credit Sale
        if (isCredit) {
            creditCustomerId = creditCustomerSelect.value;
            
            if (!creditCustomerId) {
                alert('Please select a credit customer.');
                canProceed = false;
            } else {
                const selectedOption = creditCustomerSelect.options[creditCustomerSelect.selectedIndex];
                if (selectedOption) {
                    customerName = selectedOption.textContent.split(' (')[0].trim();
                }
                
                const balance = parseFloat(selectedOption?.dataset.balance || 0);
                const limit = parseFloat(selectedOption?.dataset.limit || 0);
                const currentTotal = parseFloat(totalEl.textContent.replace(currencySymbol, '')) || 0;
                
                if ((balance + currentTotal) > limit) {
                    alert('Sale exceeds customer credit limit.');
                    canProceed = false;
                }
            }
            
            dueDate = creditDueDateInput.value;
            if (!dueDate) {
                alert('Please select a due date.');
                canProceed = false;
            }
        }

        if (!canProceed) return;

        // Prepare Data Payload
        const subtotal = parseFloat(subtotalEl.textContent.replace(currencySymbol, ''));
        const tax = parseFloat(taxEl.textContent.replace(currencySymbol, ''));
        const total = parseFloat(totalEl.textContent.replace(currencySymbol, ''));

        let sale_type = 'product';
        const hasFuel = cart.some(item => item.type === 'fuel');
        const hasProduct = cart.some(item => item.type === 'product');
        if (hasFuel && hasProduct) sale_type = 'mixed';
        else if (hasFuel) sale_type = 'fuel';

        const items = cart.map(item => {
            return {
                type: item.type,
                id: item.id,
                quantity: item.quantity,
                unit_price: item.price,
                discount_percentage: 0,
                discount_amount: 0,
                total_price: item.total
            };
        });

        const saleData = {
            invoice_number: document.getElementById('invoice-number').textContent,
            sale_date: new Date().toISOString().slice(0, 19).replace('T', ' '),
            customer_name: customerName,
            customer_phone: customerPhone,
            credit_customer_id: isCredit ? creditCustomerId : null,
            due_date: isCredit ? dueDate : null,
            is_credit: isCredit,
            sale_type: sale_type,
            staff_id: staffId || null,
            total_amount: subtotal,
            discount_amount: 0,
            tax_amount: tax,
            net_amount: total,
            payment_method: isCredit ? 'credit' : paymentMethod,
            payment_status: isCredit ? 'pending' : 'paid',
            credit_status: isCredit ? 'pending' : null,
            notes: '',
            items: items
        };

        // Submit Sale via API
        completeSaleBtn.disabled = true;
        completeSaleBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

        fetch('../../api/pos_api.php?action=processSale', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(saleData),
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse response as JSON:', e);
                    throw new Error(`Server Error: ${response.status} - ${text}`);
                }
            });
        })
        .then(data => {
            if (data.status === 'success' && data.receipt_url) {
                const printWindow = window.open(data.receipt_url, '_blank');
                if (printWindow) {
                    printWindow.onload = function() {
                        printWindow.print();
                    };
                } else {
                    alert('Pop-up blocked. Please allow pop-ups for this site to print receipts automatically. Sale completed.');
                }

                // Show success modal
                successInvoice.textContent = saleData.invoice_number;
                if (isCredit) {
                    creditSuccessInfo.classList.remove('hidden');
                    successCreditCustomer.textContent = customerName;
                    successCreditDueDate.textContent = dueDate;
                    const selectedOption = creditCustomerSelect.options[creditCustomerSelect.selectedIndex];
                    const balance = parseFloat(selectedOption?.dataset.balance || 0);
                    const newBalance = balance + total;
                    successCreditBalance.textContent = `${currencySymbol}${newBalance.toFixed(2)}`;
                } else {
                    creditSuccessInfo.classList.add('hidden');
                }
                successModal.classList.remove('hidden');
            } else {
                alert(`Error processing sale: ${data.message || 'Unknown error'}`);
            }
        })
        .catch(error => {
            console.error('Error processing sale:', error);
            alert(`Error processing sale: ${error.message || 'Network error or server issue.'}`);
        })
        .finally(() => {
            completeSaleBtn.disabled = false;
            completeSaleBtn.innerHTML = 'Complete & Print';
        });
    }

    // Handle New Sale Button (from Success Modal)
    function handleNewSale() {
        successModal.classList.add('hidden');
        resetSale();
    }

    // Reset Sale Interface completely
    function resetSale() {
        cart = [];
        updateCart();

        staffSelect.value = '';
        paymentMethodSelect.value = 'cash';
        customerNameInput.value = '';
        customerPhoneInput.value = '';
        saleTypeRegular.checked = true;
        toggleCreditSection();
        creditCustomerSelect.value = '';
        resetCreditInfo();
        setNextInvoiceNumber();
        productSearch.focus();
    }

    // Function to set the next invoice number
    function setNextInvoiceNumber() {
        const invoiceEl = document.getElementById('invoice-number');
        const currentInvoice = invoiceEl.textContent;
        try {
            const parts = currentInvoice.split('-');
            const lastNumber = parseInt(parts.pop());
            const datePart = parts.pop();
             const prefix = parts.join('-');

            const todayDate = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            let nextNumber = 1;
            if (datePart === todayDate) {
                 nextNumber = lastNumber + 1;
            }

            invoiceEl.textContent = `${prefix}-${todayDate}-${nextNumber.toString().padStart(4, '0')}`;
        } catch (e) {
            console.error("Could not parse invoice number to increment:", currentInvoice);
        }
    }

    // Helper HTML Generators
    function getLoadingIndicatorHTML() {
        return '<div class="text-center text-gray-500 col-span-full py-12"><div class="animate-spin inline-block w-8 h-8 border-4 border-gray-300 border-t-blue-600 rounded-full mb-4"></div><p>Loading...</p></div>';
    }

    function getErrorHTML(message) {
         return `<div class="text-center text-red-500 col-span-full py-12"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>${message}</p></div>`;
    }

});
</script>

<?php include_once '../../includes/footer.php'; ?>