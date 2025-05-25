<?php
/**
 * POS Module - Add New Product
 * 
 * This file contains the form to add a new product to inventory
 */

// Set page title and include header
$page_title = "Add New Product";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Point of Sale</a> / <span class="text-gray-700">Add New Product</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once 'functions.php';

// Initialize error and success messages
$errors = [];
$success = false;

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
    $product_code = trim($_POST['product_code'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $unit_id = (int)($_POST['unit_id'] ?? 0); // Changed from unit to unit_id
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $current_stock = (int)($_POST['current_stock'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate product code
    if (empty($product_code)) {
        $errors[] = "Product code is required.";
    } else {
        // Check if product code already exists
        $stmt = $conn->prepare("SELECT product_id FROM products WHERE product_code = ?");
        if ($stmt) {
            $stmt->bind_param("s", $product_code);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Product code already exists. Please use a different code.";
            }
            $stmt->close();
        }
    }
    
    // Validate other required fields
    if (empty($product_name)) {
        $errors[] = "Product name is required.";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a valid category.";
    }
    
    if ($unit_id <= 0) { // Changed from unit to unit_id
        $errors[] = "Please select a valid unit.";
    }
    
    if ($purchase_price <= 0) {
        $errors[] = "Purchase price must be greater than zero.";
    }
    
    if ($selling_price <= 0) {
        $errors[] = "Selling price must be greater than zero.";
    }
    
    if ($selling_price < $purchase_price) {
        $errors[] = "Selling price should not be less than purchase price.";
    }
    
    if ($reorder_level < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }
    
    if ($current_stock < 0) {
        $errors[] = "Current stock cannot be negative.";
    }
    
    // If no errors, insert product
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Insert product
            $stmt = $conn->prepare("
                INSERT INTO products (
                    product_code, product_name, category_id, description, unit_id,
                    purchase_price, selling_price, current_stock, reorder_level,
                    barcode, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt) {
                $stmt->bind_param(
                    "ssissddiiss",
                    $product_code, $product_name, $category_id, $description, $unit_id,
                    $purchase_price, $selling_price, $current_stock, $reorder_level,
                    $barcode, $status
                );
                
                $stmt->execute();
                $product_id = $conn->insert_id;
                $stmt->close();
                
                // If adding with initial stock, create inventory transaction
                if ($current_stock > 0 && $product_id > 0) {
                    // Record inventory transaction
                    $stmt = $conn->prepare("
                        INSERT INTO inventory_transactions (
                            product_id, transaction_type, reference_id, previous_quantity,
                            change_quantity, new_quantity, transaction_date, conducted_by
                        ) VALUES (?, 'purchase', NULL, 0, ?, ?, NOW(), ?)
                    ");
                    
                    $user_id = $_SESSION['user_id'];
                    if ($stmt) {
                        $stmt->bind_param("iiii", $product_id, $current_stock, $current_stock, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $conn->commit();
                $success = true;
                
                // Set success message and redirect
                $_SESSION['success_message'] = "Product added successfully.";
                header("Location: view_products.php");
                exit;
            } else {
                $errors[] = "Error preparing statement: " . $conn->error;
                $conn->rollback();
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
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
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-xl font-semibold text-gray-800">Add New Product</h3>
            <p class="text-sm text-gray-500 mt-1">Enter product details below</p>
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
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Product code -->
                <div>
                    <label for="product_code" class="block text-sm font-medium text-gray-700 mb-1">
                        Product Code <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="product_code" id="product_code" required
                           value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="e.g. OIL001">
                </div>
                
                <!-- Product name -->
                <div>
                    <label for="product_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Product Name <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="product_name" id="product_name" required
                           value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="e.g. Engine Oil 5W-30">
                </div>
                
                <!-- Category with Add New Category button -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Category <span class="text-red-600">*</span>
                    </label>
                    <div class="flex space-x-2">
                        <select name="category_id" id="category_id" required
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" <?= isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="manage-categories-btn" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-plus mr-1"></i> Manage
                        </button>
                    </div>
                </div>
                
                <!-- Unit Dropdown -->
                <div>
                    <label for="unit_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Unit <span class="text-red-600">*</span>
                    </label>
                    <div class="flex space-x-2">
                        <select name="unit_id" id="unit_id" required
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select Unit</option>
                            <?php 
                            // Get all active units
                            $unitStmt = $conn->prepare("SELECT unit_id, unit_name, unit_symbol FROM units WHERE status = 'active' ORDER BY unit_name");
                            $unitStmt->execute();
                            $unitResult = $unitStmt->get_result();
                            while ($unit = $unitResult->fetch_assoc()): 
                            ?>
                            <option value="<?= $unit['unit_id'] ?>" <?= isset($_POST['unit_id']) && $_POST['unit_id'] == $unit['unit_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['unit_symbol']) ?>)
                            </option>
                            <?php endwhile; $unitStmt->close(); ?>
                        </select>
                        <button type="button" id="manage-units-btn" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-plus mr-1"></i> Manage
                        </button>
                    </div>
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
                               value="<?= htmlspecialchars($_POST['purchase_price'] ?? '') ?>"
                               min="0" step="0.01"
                               class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md"
                               placeholder="0.00">
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
                               value="<?= htmlspecialchars($_POST['selling_price'] ?? '') ?>"
                               min="0" step="0.01"
                               class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md"
                               placeholder="0.00">
                    </div>
                </div>
                
                <!-- Current stock -->
                <div>
                    <label for="current_stock" class="block text-sm font-medium text-gray-700 mb-1">
                        Initial Stock Quantity
                    </label>
                    <input type="number" name="current_stock" id="current_stock"
                           value="<?= htmlspecialchars($_POST['current_stock'] ?? '0') ?>"
                           min="0" step="1"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="0">
                </div>
                
                <!-- Reorder level -->
                <div>
                    <label for="reorder_level" class="block text-sm font-medium text-gray-700 mb-1">
                        Reorder Level <span class="text-red-600">*</span>
                    </label>
                    <input type="number" name="reorder_level" id="reorder_level" required
                           value="<?= htmlspecialchars($_POST['reorder_level'] ?? '5') ?>"
                           min="0" step="1"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="5">
                </div>
                
                <!-- Barcode -->
                <div>
                    <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">
                        Barcode (Optional)
                    </label>
                    <input type="text" name="barcode" id="barcode"
                           value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="e.g. 5901234123457">
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-600">*</span>
                    </label>
                    <select name="status" id="status" required
                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="active" <?= isset($_POST['status']) && $_POST['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= isset($_POST['status']) && $_POST['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <!-- Description field spans both columns -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                    </label>
                    <textarea name="description" id="description" rows="3"
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="Product description (optional)"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Form buttons -->
            <div class="mt-6 flex justify-end space-x-3">
                <a href="view_products.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </a>
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Category Management Modal -->
<div id="category-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Manage Categories</h3>
            <button type="button" id="close-category-modal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="p-6">
            <!-- Add New Category Form -->
            <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Add New Category</h4>
                <form id="add-category-form" class="space-y-4">
                    <div>
                        <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Category Name <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="category_name" name="category_name" required
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                               placeholder="e.g. Engine Oils">
                    </div>
                    
                    <div>
                        <label for="category_description" class="block text-sm font-medium text-gray-700 mb-1">
                            Description (Optional)
                        </label>
                        <textarea id="category_description" name="category_description" rows="2"
                                  class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                  placeholder="Category description"></textarea>
                    </div>
                    
                    <div>
                        <label for="category_status" class="block text-sm font-medium text-gray-700 mb-1">
                            Status
                        </label>
                        <select id="category_status" name="category_status"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <button type="submit" id="save-category-btn" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Add Category
                    </button>
                </form>
            </div>
            
            <!-- Category List -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Existing Categories</h4>
                <div id="categories-list" class="max-h-60 overflow-y-auto">
                    <div class="animate-pulse flex flex-col space-y-2">
                        <div class="h-10 bg-gray-200 rounded w-full"></div>
                        <div class="h-10 bg-gray-200 rounded w-full"></div>
                        <div class="h-10 bg-gray-200 rounded w-full"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
            <button type="button" id="done-btn" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                Done
            </button>
        </div>
    </div>
</div>

<!-- Quick Add Unit Modal -->
<div id="unit-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Add New Unit</h3>
        </div>
        
        <form id="unit-form" class="p-6 space-y-4">
            <div>
                <label for="unit_name" class="block text-sm font-medium text-gray-700">Unit Name <span class="text-red-500">*</span></label>
                <input type="text" id="unit_name" name="unit_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                <p class="text-xs text-gray-500 mt-1">Example: Liter, Piece, Box, Tank, etc.</p>
            </div>
            
            <div>
                <label for="unit_symbol" class="block text-sm font-medium text-gray-700">Unit Symbol <span class="text-red-500">*</span></label>
                <input type="text" id="unit_symbol" name="unit_symbol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                <p class="text-xs text-gray-500 mt-1">Short symbol like 'L', 'Pcs', 'Box', etc.</p>
            </div>
            
            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" id="cancel-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Unit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Success Alert -->
<div id="category-success-alert" class="fixed top-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 hidden" role="alert">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm">Category added successfully!</p>
        </div>
        <div class="ml-auto pl-3">
            <div class="-mx-1.5 -my-1.5">
                <button id="close-category-alert" class="inline-flex text-green-500 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Error Alert -->
<div id="category-error-alert" class="fixed top-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 hidden" role="alert">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="ml-3">
            <p id="category-error-message" class="text-sm">Error adding category.</p>
        </div>
        <div class="ml-auto pl-3">
            <div class="-mx-1.5 -my-1.5">
                <button id="close-category-error" class="inline-flex text-red-500 focus:outline-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for category management and unit management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements for category management
    const manageCategoriesBtn = document.getElementById('manage-categories-btn');
    const categoryModal = document.getElementById('category-modal');
    const closeCategoryModalBtn = document.getElementById('close-category-modal');
    const doneBtn = document.getElementById('done-btn');
    const addCategoryForm = document.getElementById('add-category-form');
    const categoriesList = document.getElementById('categories-list');
    const categoryDropdown = document.getElementById('category_id');
    const categorySuccessAlert = document.getElementById('category-success-alert');
    const categoryErrorAlert = document.getElementById('category-error-alert');
    const categoryErrorMessage = document.getElementById('category-error-message');
    const closeCategoryAlert = document.getElementById('close-category-alert');
    const closeCategoryError = document.getElementById('close-category-error');
    
    // Elements for unit management
    const manageUnitsBtn = document.getElementById('manage-units-btn');
    const unitModal = document.getElementById('unit-modal');
    const unitForm = document.getElementById('unit-form');
    const unitNameInput = document.getElementById('unit_name');
    const unitSymbolInput = document.getElementById('unit_symbol');
    const unitSelect = document.getElementById('unit_id');
    const cancelBtn = document.getElementById('cancel-btn');
    
    // Category modal functionality
    if (manageCategoriesBtn && categoryModal) {
        // Open category modal
        manageCategoriesBtn.addEventListener('click', function() {
            categoryModal.classList.remove('hidden');
            loadCategories();
        });
        
        // Close category modal
        if (closeCategoryModalBtn) {
            closeCategoryModalBtn.addEventListener('click', function() {
                categoryModal.classList.add('hidden');
            });
        }
        
        // Done button
        if (doneBtn) {
            doneBtn.addEventListener('click', function() {
                categoryModal.classList.add('hidden');
            });
        }
        
        // Close success alert
        if (closeCategoryAlert) {
            closeCategoryAlert.addEventListener('click', function() {
                categorySuccessAlert.classList.add('hidden');
            });
        }
        
        // Close error alert
        if (closeCategoryError) {
            closeCategoryError.addEventListener('click', function() {
                categoryErrorAlert.classList.add('hidden');
            });
        }
        
        // Add new category
        if (addCategoryForm) {
            addCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const categoryName = document.getElementById('category_name').value;
                const categoryDescription = document.getElementById('category_description').value;
                const categoryStatus = document.getElementById('category_status').value;
                
                if (!categoryName.trim()) {
                    showErrorAlert('Category name is required.');
                    return;
                }
                
                // Create form data
                const formData = new FormData();
                formData.append('action', 'add_category');
                formData.append('category_name', categoryName);
                formData.append('description', categoryDescription);
                formData.append('status', categoryStatus);
                
                // Send request to server
                fetch('category_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        showSuccessAlert();
                        
                        // Reset form
                        addCategoryForm.reset();
                        
                        // Reload categories
                        loadCategories();
                        
                        // Add new category to dropdown
                        const option = document.createElement('option');
                        option.value = data.category_id;
                        option.text = categoryName;
                        option.selected = true;
                        categoryDropdown.appendChild(option);
                    } else {
                        showErrorAlert(data.message || 'Error adding category.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorAlert('An unexpected error occurred. Please try again.');
                });
            });
        }
        
        // Load categories function
        function loadCategories() {
            // Clear previous content and show loading
            if (categoriesList) {
                categoriesList.innerHTML = `
                    <div class="animate-pulse flex flex-col space-y-2">
                        <div class="h-10 bg-gray-200 rounded w-full"></div>
                        <div class="h-10 bg-gray-200 rounded w-full"></div>
                        <div class="h-10 bg-gray-200 rounded w-full"></div>
                    </div>
                `;
                
                // Fetch categories from server
                fetch('category_ajax.php?action=get_categories')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (data.categories.length === 0) {
                                categoriesList.innerHTML = '<p class="text-gray-500 text-center py-4">No categories found.</p>';
                                return;
                            }
                            
                            // Build category list
                            let html = '<ul class="divide-y divide-gray-200">';
                            
                            data.categories.forEach(category => {
                                const statusClass = category.status === 'active' 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-gray-100 text-gray-800';
                                
                                html += `
                                    <li class="py-3 flex justify-between items-center">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">${category.category_name}</p>
                                            <p class="text-xs text-gray-500">${category.description || 'No description'}</p>
                                        </div>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                            ${category.status}
                                        </span>
                                    </li>
                                `;
                            });
                            
                            html += '</ul>';
                            categoriesList.innerHTML = html;
                        } else {
                            categoriesList.innerHTML = '<p class="text-red-500 text-center py-4">Error loading categories.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        categoriesList.innerHTML = '<p class="text-red-500 text-center py-4">Error loading categories.</p>';
                    });
            }
        }
        
        // Show success alert
        function showSuccessAlert() {
            if (categorySuccessAlert) {
                categorySuccessAlert.classList.remove('hidden');
                
                // Auto hide after 3 seconds
                setTimeout(() => {
                    categorySuccessAlert.classList.add('hidden');
                }, 3000);
            }
        }
        
        // Show error alert
        function showErrorAlert(message) {
            if (categoryErrorMessage && categoryErrorAlert) {
                categoryErrorMessage.textContent = message;
                categoryErrorAlert.classList.remove('hidden');
                
                // Auto hide after 5 seconds
                setTimeout(() => {
                    categoryErrorAlert.classList.add('hidden');
                }, 5000);
            }
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === categoryModal) {
                categoryModal.classList.add('hidden');
            }
        });
        
        // Prevent propagation from modal content
        const categoryModalContent = categoryModal.querySelector('.bg-white');
        if (categoryModalContent) {
            categoryModalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }
    
    // Unit Modal Functionality
    if (manageUnitsBtn && unitModal) {
        // Open unit modal
        manageUnitsBtn.addEventListener('click', function() {
            unitModal.classList.remove('hidden');
        });
        
        // Cancel button for unit modal
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                unitModal.classList.add('hidden');
            });
        }
        
        // Unit form submission
        if (unitForm) {
            unitForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!unitNameInput.value.trim() || !unitSymbolInput.value.trim()) {
                    alert('Please enter both unit name and symbol');
                    return;
                }
                
                // Prepare form data
                const formData = new FormData();
                formData.append('action', 'add_unit');
                formData.append('unit_name', unitNameInput.value);
                formData.append('unit_symbol', unitSymbolInput.value);
                formData.append('status', 'active');
                
                // Send AJAX request
                fetch('../../modules/settings/unit_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        alert('Unit added successfully!');
                        
                        // Add new unit to dropdown
                        const option = document.createElement('option');
                        option.value = data.unit_id;
                        option.text = `${data.unit_name} (${data.unit_symbol})`;
                        option.selected = true;
                        unitSelect.appendChild(option);
                        
                        // Close modal and reset form
                        unitModal.classList.add('hidden');
                        unitForm.reset();
                    } else {
                        // Show error message
                        alert(data.message || 'Error adding unit');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An unexpected error occurred. Please try again.');
                });
            });
        }
        
        // Close unit modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === unitModal) {
                unitModal.classList.add('hidden');
            }
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>