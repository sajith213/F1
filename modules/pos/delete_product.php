<?php
/**
 * POS Module - Delete Product
 * 
 * This file handles the deletion of a product from the database
 */

// Include database connection
require_once '../../includes/db.php';
session_start();

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid product ID.";
    header("Location: view_products.php");
    exit;
}

$product_id = (int)$_GET['id'];

// Validate that the product exists
$stmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: view_products.php");
    exit;
}

// Get the product name for the success message
$product_name = $result->fetch_assoc()['product_name'];
$stmt->close();

// Try to delete the product
try {
    $delete_stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $delete_stmt->bind_param("i", $product_id);
    $success = $delete_stmt->execute();
    $delete_stmt->close();
    
    if ($success) {
        $_SESSION['success_message'] = "Product '{$product_name}' has been successfully deleted.";
    } else {
        $_SESSION['error_message'] = "Failed to delete product: " . $conn->error;
    }
} catch (mysqli_sql_exception $e) {
    // If deletion fails due to foreign key constraints, mark as inactive instead
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $update_stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE product_id = ?");
        $update_stmt->bind_param("i", $product_id);
        $success = $update_stmt->execute();
        $update_stmt->close();
        
        if ($success) {
            $_SESSION['success_message'] = "Product '{$product_name}' has been marked as inactive because it is referenced in other records.";
        } else {
            $_SESSION['error_message'] = "Could not delete or deactivate product: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Error deleting product: " . $e->getMessage();
    }
}

// Redirect back to product list
header("Location: view_products.php");
exit;
?>