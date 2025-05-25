<?php
/**
 * Petty Cash Management - Categories with Sub-Categories
 * 
 * Manage petty cash categories and sub-categories
 */

// Set page title
$page_title = "Petty Cash Categories";
$breadcrumbs = '<a href="../../index.php">Home</a> > <a href="index.php">Petty Cash</a> > Categories';

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Include auth functions
require_once '../../includes/auth.php';

// Check permission
if (!has_permission('manage_petty_cash')) {
    // Redirect to dashboard or show error
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
    echo '<p>You do not have permission to access this module.</p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Initialize variables
$categories = [];
$parent_categories = [];
$errors = [];
$success_message = '';
$category_to_edit = null;

// Helper function to build hierarchical category array
function buildCategoryTree($categories) {
    $tree = [];
    $indexed = [];
    
    // First, index all categories by ID
    foreach ($categories as $category) {
        $category['children'] = [];
        $indexed[$category['category_id']] = $category;
    }
    
    // Then, build the tree structure
    foreach ($indexed as $category) {
        if ($category['parent_category_id'] === null) {
            // This is a root category
            $tree[$category['category_id']] = $category;
        } else {
            // This is a sub-category
            if (isset($indexed[$category['parent_category_id']])) {
                $indexed[$category['parent_category_id']]['children'][] = $category;
                $tree[$category['parent_category_id']] = $indexed[$category['parent_category_id']];
            }
        }
    }
    
    return $tree;
}

// Helper function to check if a category can be a parent of another (prevent circular references)
function canBeParent($potential_parent_id, $category_id, $conn) {
    if ($potential_parent_id == $category_id) {
        return false; // Can't be parent of itself
    }
    
    // Check if potential parent is actually a child of the category (would create circular reference)
    $current_id = $potential_parent_id;
    while ($current_id !== null) {
        $query = "SELECT parent_category_id FROM petty_cash_categories WHERE category_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['parent_category_id'] == $category_id) {
                return false; // Circular reference detected
            }
            $current_id = $row['parent_category_id'];
        } else {
            break;
        }
    }
    
    return true;
}

// Process category deletion
if (isset($_GET['delete']) && !empty($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    if (isset($conn) && $conn) {
        try {
            // Check if category has sub-categories
            $sub_check_query = "SELECT COUNT(*) as count FROM petty_cash_categories WHERE parent_category_id = ?";
            $stmt = $conn->prepare($sub_check_query);
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $sub_row = $result->fetch_assoc();
            
            if ($sub_row['count'] > 0) {
                $errors[] = "Cannot delete category with sub-categories. Please delete or move sub-categories first.";
            } else {
                // Check if category is in use in transactions
                $check_query = "SELECT COUNT(*) as count FROM petty_cash WHERE category_id = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("i", $category_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    // Category is in use, set to inactive instead of deleting
                    $update_query = "UPDATE petty_cash_categories SET status = 'inactive' WHERE category_id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("i", $category_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Category deactivated successfully.";
                    } else {
                        $errors[] = "Failed to deactivate category.";
                    }
                } else {
                    // Category not in use, safe to delete
                    $delete_query = "DELETE FROM petty_cash_categories WHERE category_id = ?";
                    $stmt = $conn->prepare($delete_query);
                    $stmt->bind_param("i", $category_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Category deleted successfully.";
                    } else {
                        $errors[] = "Failed to delete category.";
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get category to edit
if (isset($_GET['edit']) && !empty($_GET['edit']) && is_numeric($_GET['edit'])) {
    $category_id = $_GET['edit'];
    
    if (isset($conn) && $conn) {
        try {
            $query = "SELECT * FROM petty_cash_categories WHERE category_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $category_to_edit = $row;
            } else {
                $errors[] = "Category not found.";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    $category_type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $parent_category_id = isset($_POST['parent_category_id']) && !empty($_POST['parent_category_id']) ? intval($_POST['parent_category_id']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    
    // Validate input
    if (empty($category_name)) {
        $errors[] = "Category name is required";
    }
    
    if (empty($category_type)) {
        $errors[] = "Category type is required";
    } elseif (!in_array($category_type, ['income', 'expense'])) {
        $errors[] = "Invalid category type";
    }
    
    if (empty($status)) {
        $errors[] = "Status is required";
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Invalid status";
    }
    
    // Validate parent category if provided
    if ($parent_category_id !== null && isset($conn) && $conn) {
        // Check if parent category exists and has the same type
        $parent_check_query = "SELECT type FROM petty_cash_categories WHERE category_id = ? AND status = 'active'";
        $stmt = $conn->prepare($parent_check_query);
        $stmt->bind_param("i", $parent_category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['type'] !== $category_type) {
                $errors[] = "Sub-category must have the same type as its parent category";
            }
            
            // Check for circular reference if editing
            if ($category_id > 0 && !canBeParent($parent_category_id, $category_id, $conn)) {
                $errors[] = "Invalid parent selection - would create circular reference";
            }
        } else {
            $errors[] = "Selected parent category not found or inactive";
        }
    }
    
    // If no errors, save or update the category
    if (empty($errors) && isset($conn) && $conn) {
        try {
            if ($category_id > 0) {
                // Update existing category
                $query = "UPDATE petty_cash_categories SET 
                          category_name = ?, 
                          type = ?, 
                          parent_category_id = ?,
                          description = ?, 
                          status = ? 
                          WHERE category_id = ?";
                          
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssissi", $category_name, $category_type, $parent_category_id, $description, $status, $category_id);
                
                if ($stmt->execute()) {
                    $success_message = "Category updated successfully";
                    // Clear edit mode
                    $category_to_edit = null;
                } else {
                    $errors[] = "Failed to update category: " . $stmt->error;
                }
            } else {
                // Check if category with same name, type and parent already exists
                $check_query = "SELECT COUNT(*) as count FROM petty_cash_categories WHERE category_name = ? AND type = ? AND parent_category_id " . ($parent_category_id ? "= ?" : "IS NULL");
                $stmt = $conn->prepare($check_query);
                
                if ($parent_category_id) {
                    $stmt->bind_param("ssi", $category_name, $category_type, $parent_category_id);
                } else {
                    $stmt->bind_param("ss", $category_name, $category_type);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    $errors[] = "A category with this name, type and parent already exists";
                } else {
                    // Insert new category
                    $query = "INSERT INTO petty_cash_categories (category_name, type, parent_category_id, description, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())";
                              
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssiss", $category_name, $category_type, $parent_category_id, $description, $status);
                    
                    if ($stmt->execute()) {
                        $success_message = "Category added successfully";
                    } else {
                        $errors[] = "Failed to add category: " . $stmt->error;
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Fetch all categories
if (isset($conn) && $conn) {
    try {
        $query = "SELECT * FROM petty_cash_categories ORDER BY type, COALESCE(parent_category_id, category_id), parent_category_id IS NOT NULL, category_name";
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
        
        // Get parent categories for dropdown (only main categories)
        $parent_query = "SELECT * FROM petty_cash_categories WHERE parent_category_id IS NULL AND status = 'active' ORDER BY type, category_name";
        $result = $conn->query($parent_query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $parent_categories[] = $row;
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Failed to fetch categories: " . $e->getMessage();
    }
}

// Build hierarchical structure for display
$category_tree = buildCategoryTree($categories);
?>

<!-- Page Content -->
<div class="mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800">Petty Cash Categories & Sub-Categories</h2>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <h3 class="font-bold">Please correct the following errors:</h3>
        <ul class="list-disc ml-5">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Category Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4"><?= $category_to_edit ? 'Edit Category' : 'Add New Category' ?></h3>
                
                <form action="" method="POST">
                    <!-- Hidden ID for edit mode -->
                    <?php if ($category_to_edit): ?>
                    <input type="hidden" name="category_id" value="<?= $category_to_edit['category_id'] ?>">
                    <?php endif; ?>
                    
                    <!-- Category Type (moved up to filter parent categories) -->
                    <div class="mb-4">
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Category Type<span class="text-red-600">*</span></label>
                        <select id="type" name="type" required onchange="filterParentCategories()"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">-- Select Type --</option>
                            <option value="income" <?= ($category_to_edit && $category_to_edit['type'] === 'income') ? 'selected' : '' ?>>Income</option>
                            <option value="expense" <?= ($category_to_edit && $category_to_edit['type'] === 'expense') ? 'selected' : '' ?>>Expense</option>
                        </select>
                    </div>
                    
                    <!-- Parent Category (Sub-category option) -->
                    <div class="mb-4">
                        <label for="parent_category_id" class="block text-sm font-medium text-gray-700 mb-1">Parent Category <small>(Leave empty for main category)</small></label>
                        <select id="parent_category_id" name="parent_category_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">-- Main Category --</option>
                            <?php foreach ($parent_categories as $parent): ?>
                            <option value="<?= $parent['category_id'] ?>" 
                                    data-type="<?= $parent['type'] ?>"
                                    <?= ($category_to_edit && $category_to_edit['parent_category_id'] == $parent['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($parent['category_name']) ?> (<?= ucfirst($parent['type']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Category Name -->
                    <div class="mb-4">
                        <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">Category Name<span class="text-red-600">*</span></label>
                        <input type="text" id="category_name" name="category_name" value="<?= $category_to_edit ? htmlspecialchars($category_to_edit['category_name']) : '' ?>" required
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= $category_to_edit ? htmlspecialchars($category_to_edit['description']) : '' ?></textarea>
                    </div>
                    
                    <!-- Status (only for edit mode) -->
                    <?php if ($category_to_edit): ?>
                    <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status<span class="text-red-600">*</span></label>
                        <select id="status" name="status" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="active" <?= $category_to_edit['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $category_to_edit['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="status" value="active">
                    <?php endif; ?>
                    
                    <!-- Submit Buttons -->
                    <div class="flex justify-between mt-6">
                        <?php if ($category_to_edit): ?>
                        <a href="categories.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                            Cancel
                        </a>
                        <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded">
                            <i class="fas fa-save mr-2"></i> Update Category
                        </button>
                        <?php else: ?>
                        <div></div> <!-- Empty div for spacing -->
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                            <i class="fas fa-plus mr-2"></i> Add Category
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Categories List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <!-- Income Categories -->
                <div class="mb-4">
                    <h3 class="text-lg font-semibold bg-green-50 p-4 border-b border-gray-200">Income Categories</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Category Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $income_tree = array_filter($category_tree, function($category) {
                                    return $category['type'] === 'income';
                                });
                                
                                if (empty($income_tree)): 
                                ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No income categories found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($income_tree as $category): ?>
                                    <!-- Main Category -->
                                    <tr class="hover:bg-gray-50 bg-green-25">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                            <i class="fas fa-folder text-green-600 mr-2"></i>
                                            <?= htmlspecialchars($category['category_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                            <?= htmlspecialchars($category['description'] ?: '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($category['status'] === 'active'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="categories.php?edit=<?= $category['category_id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="categories.php?delete=<?= $category['category_id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this category?')"
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Sub-Categories -->
                                    <?php foreach ($category['children'] as $subcategory): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 pl-12">
                                            <i class="fas fa-file text-gray-400 mr-2"></i>
                                            └─ <?= htmlspecialchars($subcategory['category_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                            <?= htmlspecialchars($subcategory['description'] ?: '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($subcategory['status'] === 'active'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="categories.php?edit=<?= $subcategory['category_id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="categories.php?delete=<?= $subcategory['category_id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this sub-category?')"
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Expense Categories -->
                <div>
                    <h3 class="text-lg font-semibold bg-red-50 p-4 border-b border-gray-200">Expense Categories</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Category Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $expense_tree = array_filter($category_tree, function($category) {
                                    return $category['type'] === 'expense';
                                });
                                
                                if (empty($expense_tree)): 
                                ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No expense categories found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($expense_tree as $category): ?>
                                    <!-- Main Category -->
                                    <tr class="hover:bg-gray-50 bg-red-25">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                            <i class="fas fa-folder text-red-600 mr-2"></i>
                                            <?= htmlspecialchars($category['category_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                            <?= htmlspecialchars($category['description'] ?: '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($category['status'] === 'active'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="categories.php?edit=<?= $category['category_id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="categories.php?delete=<?= $category['category_id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this category?')"
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Sub-Categories -->
                                    <?php foreach ($category['children'] as $subcategory): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 pl-12">
                                            <i class="fas fa-file text-gray-400 mr-2"></i>
                                            └─ <?= htmlspecialchars($subcategory['category_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                            <?= htmlspecialchars($subcategory['description'] ?: '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($subcategory['status'] === 'active'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="categories.php?edit=<?= $subcategory['category_id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="categories.php?delete=<?= $subcategory['category_id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this sub-category?')"
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterParentCategories() {
    const typeSelect = document.getElementById('type');
    const parentSelect = document.getElementById('parent_category_id');
    const selectedType = typeSelect.value;
    
    // Show/hide parent category options based on selected type
    Array.from(parentSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block'; // Always show "Main Category" option
        } else {
            const optionType = option.getAttribute('data-type');
            option.style.display = (optionType === selectedType) ? 'block' : 'none';
        }
    });
    
    // Reset parent selection if current selection is not valid for the new type
    const currentParent = parentSelect.value;
    if (currentParent && parentSelect.querySelector(`option[value="${currentParent}"]`).style.display === 'none') {
        parentSelect.value = '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    filterParentCategories();
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>