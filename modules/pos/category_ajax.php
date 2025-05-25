<?php
/**
 * Category AJAX Handler
 * 
 * This file handles AJAX requests for category management
 */

// Start session
session_start();

// Include database connection
require_once '../../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Determine the action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_categories':
        getCategories();
        break;
    
    case 'add_category':
        addCategory();
        break;
    
    case 'update_category':
        updateCategory();
        break;
    
    case 'delete_category':
        deleteCategory();
        break;
    
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
        break;
}

/**
 * Get all categories
 */
function getCategories() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT category_id, category_name, description, status FROM product_categories ORDER BY category_name");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'categories' => $categories
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch categories: ' . $conn->error
        ]);
    }
}

/**
 * Add a new category
 */
function addCategory() {
    global $conn;
    
    // Get category data
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate input
    if (empty($category_name)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Category name is required'
        ]);
        return;
    }
    
    // Check if category already exists
    $stmt = $conn->prepare("SELECT category_id FROM product_categories WHERE category_name = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Category with this name already exists'
            ]);
            $stmt->close();
            return;
        }
        
        $stmt->close();
    }
    
    // Insert new category
    $stmt = $conn->prepare("INSERT INTO product_categories (category_name, description, status) VALUES (?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sss", $category_name, $description, $status);
        
        if ($stmt->execute()) {
            $category_id = $conn->insert_id;
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Category added successfully',
                'category_id' => $category_id,
                'category_name' => $category_name
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to add category: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
    }
}

/**
 * Update an existing category
 */
function updateCategory() {
    global $conn;
    
    // Get category data
    $category_id = (int)($_POST['category_id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate input
    if ($category_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid category ID'
        ]);
        return;
    }
    
    if (empty($category_name)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Category name is required'
        ]);
        return;
    }
    
    // Check if category name already exists for another category
    $stmt = $conn->prepare("SELECT category_id FROM product_categories WHERE category_name = ? AND category_id != ?");
    
    if ($stmt) {
        $stmt->bind_param("si", $category_name, $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Another category with this name already exists'
            ]);
            $stmt->close();
            return;
        }
        
        $stmt->close();
    }
    
    // Update category
    $stmt = $conn->prepare("UPDATE product_categories SET category_name = ?, description = ?, status = ? WHERE category_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("sssi", $category_name, $description, $status, $category_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Category updated successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update category: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
    }
}

/**
 * Delete a category
 */
function deleteCategory() {
    global $conn;
    
    // Get category ID
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    // Validate input
    if ($category_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid category ID'
        ]);
        return;
    }
    
    // Check if category is in use
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This category cannot be deleted because it is being used by products'
            ]);
            $stmt->close();
            return;
        }
        
        $stmt->close();
    }
    
    // Delete category
    $stmt = $conn->prepare("DELETE FROM product_categories WHERE category_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $category_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete category: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare statement: ' . $conn->error
        ]);
    }
}