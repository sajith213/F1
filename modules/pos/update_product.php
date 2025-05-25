<?php
/**
 * POS Module - Update Product
 * 
 * This file contains the form to edit an existing product
 */

// Set page title and include header
$page_title = "Update Product";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Point of Sale</a> / <span class="text-gray-700">Update Product</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once 'functions.php';

// Initialize error and success messages
$errors = [];
$success = false;

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if product exists
$product = null;
if ($product_id > 0) {
    $stmt = $conn->prepare("
        SELECT p.*, c.category_name 
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.category_id
        WHERE p.product_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $product = $result->fetch_assoc();
        } else {
            // Product not found, redirect to products list
            $_SESSION['error_message'] = "Product not found.";
            header("Location: view_products.php");
            exit;
        }
        $stmt->close();

    }
}

// Get all product categories
$categories = [];
$stmt = $conn->prepare("SELECT category_id, category_name FROM product_categories WHERE status = 'active' ORDER BY category_name");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate required fields
    if (empty($product_name)) {
        $errors[] = "Product name is required.";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a valid category.";
    }
    
    if (empty($unit)) {
        $errors[] = "Unit type is required.";
    }
    
    if ($purchase_price <= 0) {
        $errors[] = "Purchase price must be greater than zero.";
    }
    
    if ($selling_price <= 0) {
        $errors[] = "Selling price must be greater than zero.";
    }
    
    if ($reorder_level < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }
    
    // If no errors, update product
    if (empty($errors)) {
        try {
            // Update product
            $stmt = $conn->prepare("
                UPDATE products SET 
                    product_name = ?, 
                    category_id = ?, 
                    description = ?, 
                    unit = ?,
                    purchase_price = ?, 
                    selling_price = ?, 
                    reorder_level = ?,
                    barcode = ?, 
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE product_id = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param(
                    "sissddissi",
                    $product_name, $category_id, $description, $unit,
                    $purchase_price, $selling_price, $reorder_level,
                    $barcode, $status, $product_id
                );
                
                $stmt->execute();
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                if ($affected_rows > 0) {
                    // Set success message and redirect
                    $_SESSION['success_message'] = "Product updated successfully.";
                    header("Location: view_products.php");
                    exit;
                } else {
                    $errors[] = "No changes were made or product not found.";
                }
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Check if we need to show a success message
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// If product not found, show error message
if (!$product) {
    $error_message = "Product not found or has been deleted.";
}

// Get currency symbol from settings
$currency_symbol = 'LKR';
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currency_symbol = $row['setting_value'];
    }
    $stmt->close();
}
?>

<div class="container mx-auto pb-6">
    <?php if (!$product): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
            <div class="mt-3">
                <a href="view_products.php" class="text-red-700 underline">Return to products list</a>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800">Update Product</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Editing: <span class="font-medium"><?= htmlspecialchars($product['product_code']) ?> - <?= htmlspecialchars($product['product_name']) ?></span>
                    </p>
                </div>
                <div class="flex">
                    <a href="view_products.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Products
                    </a>
                </div>
            </div>
            
            <form method="POST" action="" class="p-6">
                <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Please fix the following errors:</strong>
                    <ul class="mt-1 ml-4 list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Product code (read-only) -->
                    <div>
                        <label for="product_code" class="block text-sm font-medium text-gray-700 mb-1">
                            Product Code
                        </label>
                        <input type="text" id="product_code" value="<?= htmlspecialchars($product['product_code']) ?>"
                               class="shadow-sm bg-gray-100 block w-full sm:text-sm border-gray-300 rounded-md" readonly>
                    </div>
                    
                    <!-- Product name -->
                    <div>
                        <label for="product_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Product Name <span class="text-red-600">*</span>
                        </label>
                        <input type="text" name="product_name" id="product_name" required
                               value="<?= htmlspecialchars($_POST['product_name'] ?? $product['product_name']) ?>"
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Category -->
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Category <span class="text-red-600">*</span>
                        </label>
                        <select name="category_id" id="category_id" required
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" 
                                <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) || 
                                   (!isset($_POST['category_id']) && $product['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Unit -->
                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">
                            Unit <span class="text-red-600">*</span>
                        </label>
                        <input type="text" name="unit" id="unit" required
                               value="<?= htmlspecialchars($_POST['unit'] ?? $product['unit']) ?>"
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Purchase price -->
                    <div>
                        <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-1">
                            Purchase Price <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                            </div>
                            <input type="number" name="purchase_price" id="purchase_price" required
                                   value="<?= htmlspecialchars($_POST['purchase_price'] ?? $product['purchase_price']) ?>"
                                   min="0" step="0.01"
                                   class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Selling price -->
                    <div>
                        <label for="selling_price" class="block text-sm font-medium text-gray-700 mb-1">
                            Selling Price <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                            </div>
                            <input type="number" name="selling_price" id="selling_price" required
                                   value="<?= htmlspecialchars($_POST['selling_price'] ?? $product['selling_price']) ?>"
                                   min="0" step="0.01"
                                   class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Current stock (read-only, can be updated via inventory adjustments) -->
                    <div>
                        <label for="current_stock" class="block text-sm font-medium text-gray-700 mb-1">
                            Current Stock
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="number" id="current_stock" value="<?= htmlspecialchars($product['current_stock']) ?>"
                                   class="shadow-sm bg-gray-100 block w-full sm:text-sm border-gray-300 rounded-md" readonly>
                            <a href="#" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-plus-circle"></i> Adjust Stock
                            </a>
                        </div>
                    </div>
                    
                    <!-- Reorder level -->
                    <div>
                        <label for="reorder_level" class="block text-sm font-medium text-gray-700 mb-1">
                            Reorder Level <span class="text-red-600">*</span>
                        </label>
                        <input type="number" name="reorder_level" id="reorder_level" required
                               value="<?= htmlspecialchars($_POST['reorder_level'] ?? $product['reorder_level']) ?>"
                               min="0" step="1"
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Barcode -->
                    <div>
                        <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">
                            Barcode (Optional)
                        </label>
                        <input type="text" name="barcode" id="barcode"
                               value="<?= htmlspecialchars($_POST['barcode'] ?? $product['barcode']) ?>"
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                            Status <span class="text-red-600">*</span>
                        </label>
                        <select name="status" id="status" required
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="active" <?= (isset($_POST['status']) && $_POST['status'] == 'active') || (!isset($_POST['status']) && $product['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') || (!isset($_POST['status']) && $product['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="out_of_stock" <?= (isset($_POST['status']) && $_POST['status'] == 'out_of_stock') || (!isset($_POST['status']) && $product['status'] == 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <!-- Description field spans both columns -->
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea name="description" id="description" rows="3"
                                  class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"><?= htmlspecialchars($_POST['description'] ?? $product['description']) ?></textarea>
                    </div>
                </div>
                
                <!-- Additional product info (read-only) -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 bg-gray-50 p-4 rounded-md">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Last Updated</p>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y g:i A', strtotime($product['updated_at'])) ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Created At</p>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y g:i A', strtotime($product['created_at'])) ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Profit Margin</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= $currency_symbol ?> <?= number_format($product['profit_margin'], 2) ?> 
                            (<?= number_format($product['profit_percentage'], 2) ?>%)
                        </p>
                    </div>
                </div>
                
                <!-- Form buttons -->
                <div class="mt-6 flex justify-end space-x-3">
                    <a href="view_products.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Product
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?> 