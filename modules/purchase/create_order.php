<?php
ob_start();
/**
 * Create Purchase Order
 */
$page_title = "Create Purchase Order";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Purchase Orders</a> / <span class="text-gray-700">Create Order</span>';
include_once '../../includes/header.php';
require_once '../../includes/db.php';

// Check permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Load suppliers
$suppliers = [];
$stmt = $conn->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    $stmt->close();
}

// Load products
$products = [];
$stmt = $conn->prepare("
    SELECT p.product_id, p.product_code, p.product_name, p.purchase_price, p.current_stock,
           COALESCE(pu.unit_symbol, p.unit) as purchase_unit
    FROM products p
    LEFT JOIN units pu ON p.purchase_unit_id = pu.unit_id
    WHERE p.status = 'active'
    ORDER BY p.product_name
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}

// Generate new PO number
$po_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
$stmt = $conn->prepare("SELECT po_number FROM product_purchase_orders ORDER BY po_id DESC LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $last_po = $row['po_number'];
        $parts = explode('-', $last_po);
        if (count($parts) == 3 && $parts[1] == date('Ymd')) {
            $last_num = (int)$parts[2];
            $po_number = 'PO-' . date('Ymd') . '-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        }
    }
    $stmt->close();
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    try {
        $conn->begin_transaction();
        
        // Extract form data
        $po_number = $_POST['po_number'];
        $supplier_id = $_POST['supplier_id'];
        $order_date = $_POST['order_date'];
        $expected_delivery = $_POST['expected_delivery'] ?? null;
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        $products = isset($_POST['products']) ? $_POST['products'] : [];
        $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
        $prices = isset($_POST['prices']) ? $_POST['prices'] : [];
        
        // Validate required fields
        if (empty($po_number) || empty($supplier_id) || empty($order_date) || empty($status)) {
            throw new Exception("Please fill all required fields");
        }
        
        // Validate product items
        if (empty($products) || count($products) === 0) {
            throw new Exception("Please add at least one product to the order");
        }
        
        // Calculate total amount
        $total_amount = 0;
        for ($i = 0; $i < count($products); $i++) {
            $product_id = $products[$i];
            $quantity = $quantities[$i];
            $price = $prices[$i];
            
            if (empty($product_id) || empty($quantity) || empty($price)) {
                throw new Exception("Invalid product details");
            }
            
            $total_amount += $quantity * $price;
        }
        
        // Insert purchase order
        $stmt = $conn->prepare("
            INSERT INTO product_purchase_orders (po_number, supplier_id, order_date, delivery_date, status, total_amount, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param(
            "sisssdsi",
            $po_number, $supplier_id, $order_date, $expected_delivery, $status, $total_amount, $notes, $user_id
        );
        
        $stmt->execute();
        $po_id = $conn->insert_id;
        $stmt->close();
        
        // Insert purchase order items
        $stmt = $conn->prepare("
            INSERT INTO product_purchase_items (po_id, product_id, quantity, unit_price)
            VALUES (?, ?, ?, ?)
        ");
        
        for ($i = 0; $i < count($products); $i++) {
            $product_id = $products[$i];
            $quantity = $quantities[$i];
            $price = $prices[$i];
            
            $stmt->bind_param("iidd", $po_id, $product_id, $quantity, $price);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        $success_message = "Purchase order created successfully";
        
        // Redirect after short delay
        header("refresh:2;url=view_order.php?id=" . $po_id);
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error creating purchase order: " . $e->getMessage();
    }
}
?>

<!-- Main content -->
<div class="container mx-auto pb-6">
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($error_message) ?></p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Create Purchase Order</h2>
            <p class="text-sm text-gray-600">Add a new purchase order to the system</p>
        </div>
        
        <form action="" method="POST" id="create-po-form">
            <div class="p-6 space-y-6">
                <!-- Order details -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label for="po_number" class="block text-sm font-medium text-gray-700">PO Number <span class="text-red-500">*</span></label>
                        <input type="text" id="po_number" name="po_number" value="<?= htmlspecialchars($po_number) ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier <span class="text-red-500">*</span></label>
                        <select id="supplier_id" name="supplier_id" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                        <select id="status" name="status" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                            <option value="draft">Draft</option>
                            <option value="ordered">Ordered</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="order_date" class="block text-sm font-medium text-gray-700">Order Date <span class="text-red-500">*</span></label>
                        <input type="date" id="order_date" name="order_date" value="<?= date('Y-m-d') ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                    
                    <div>
                        <label for="expected_delivery" class="block text-sm font-medium text-gray-700">Expected Delivery Date</label>
                        <input type="date" id="expected_delivery" name="expected_delivery" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                </div>
                
                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea id="notes" name="notes" rows="3" 
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                </div>
                
                <!-- Product Items -->
                <div class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Order Items</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="items-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="item-rows">
                                <tr class="item-row">
                                    <td class="px-4 py-3">
                                        <select name="products[]" class="product-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                            <option value="">Select Product</option>
                                            <?php foreach ($products as $product): ?>
                                            <option value="<?= $product['product_id'] ?>" 
                                                    data-price="<?= $product['purchase_price'] ?>" 
                                                    data-stock="<?= $product['current_stock'] ?>"
                                                    data-unit="<?= htmlspecialchars($product['purchase_unit']) ?>">
                                                <?= htmlspecialchars($product['product_name']) ?> (<?= htmlspecialchars($product['product_code']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="current-stock">-</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="unit-display">-</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="quantities[]" min="0.01" step="0.01" class="quantity-input w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="prices[]" min="0.01" step="0.01" class="price-input w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="item-total font-medium">0.00</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" class="remove-item text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <button type="button" id="add-item" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus mr-2"></i> Add Item
                        </button>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="border-t border-gray-200 pt-4">
                    <div class="flex justify-end">
                        <div class="w-full md:w-1/3">
                            <div class="flex justify-between py-2 text-gray-600">
                                <span>Total Items:</span>
                                <span id="total-items-count">1</span>
                            </div>
                            <div class="flex justify-between py-2 font-medium text-gray-900">
                                <span>Total Amount:</span>
                                <span id="total-amount">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </a>
                <button type="submit" name="create_order" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Create Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for dynamic form handling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to update item total
    function updateItemTotal(row) {
        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = quantity * price;
        row.querySelector('.item-total').textContent = total.toFixed(2);
        
        updateOrderTotal();
    }
    
    // Function to update order totals
    function updateOrderTotal() {
        const rows = document.querySelectorAll('.item-row');
        let totalAmount = 0;
        
        rows.forEach(row => {
            const itemTotal = parseFloat(row.querySelector('.item-total').textContent) || 0;
            totalAmount += itemTotal;
        });
        
        document.getElementById('total-items-count').textContent = rows.length;
        document.getElementById('total-amount').textContent = totalAmount.toFixed(2);
    }
    
    // Function to handle product selection
    function handleProductSelect(select) {
        const row = select.closest('.item-row');
        const option = select.options[select.selectedIndex];
        const price = option.dataset.price || '';
        const stock = option.dataset.stock || '0';
        const unit = option.dataset.unit || '';
        
        row.querySelector('.price-input').value = price;
        row.querySelector('.current-stock').textContent = stock;
        row.querySelector('.unit-display').textContent = unit;
        
        updateItemTotal(row);
    }
    
    // Add item button
    document.getElementById('add-item').addEventListener('click', function() {
        const itemsTable = document.getElementById('item-rows');
        const newRow = itemsTable.querySelector('.item-row').cloneNode(true);
        
        // Clear values
        newRow.querySelector('.product-select').selectedIndex = 0;
        newRow.querySelector('.quantity-input').value = '';
        newRow.querySelector('.price-input').value = '';
        newRow.querySelector('.item-total').textContent = '0.00';
        newRow.querySelector('.current-stock').textContent = '-';
        newRow.querySelector('.unit-display').textContent = '-';
        
        // Add event listeners
        addRowEventListeners(newRow);
        
        itemsTable.appendChild(newRow);
        updateOrderTotal();
    });
    
    // Function to add event listeners to a row
    function addRowEventListeners(row) {
        // Product selection
        const productSelect = row.querySelector('.product-select');
        productSelect.addEventListener('change', function() {
            handleProductSelect(this);
        });
        
        // Quantity and price inputs
        const quantityInput = row.querySelector('.quantity-input');
        const priceInput = row.querySelector('.price-input');
        
        quantityInput.addEventListener('input', function() {
            updateItemTotal(row);
        });
        
        priceInput.addEventListener('input', function() {
            updateItemTotal(row);
        });
        
        // Remove item button
        const removeBtn = row.querySelector('.remove-item');
        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                updateOrderTotal();
            } else {
                alert('You must have at least one item.');
            }
        });
    }
    
    // Add event listeners to the initial row
    const initialRow = document.querySelector('.item-row');
    addRowEventListeners(initialRow);
    
    // Form validation
    document.getElementById('create-po-form').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('.item-row');
        let valid = true;
        
        rows.forEach(row => {
            const product = row.querySelector('.product-select').value;
            const quantity = row.querySelector('.quantity-input').value;
            const price = row.querySelector('.price-input').value;
            
            if (!product || !quantity || !price) {
                valid = false;
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please complete all product details.');
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>
ob_start();