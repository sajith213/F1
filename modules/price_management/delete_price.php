<?php
/**
 * Delete Planned Price
 * 
 * This script handles deletion of planned fuel prices
 */

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check user permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    // Redirect to access denied page or display error
    header("Location: ../../index.php?error=access_denied");
    exit;
}

// Check if price ID is provided
if (!isset($_POST['price_id']) || empty($_POST['price_id'])) {
    // Redirect with error
    header("Location: index.php?error=invalid_request");
    exit;
}

$price_id = intval($_POST['price_id']);

// Delete the planned price
$result = deletePlannedPrice($price_id);

if ($result) {
    // Redirect with success message
    header("Location: index.php?success=price_deleted");
} else {
    // Redirect with error message
    header("Location: index.php?error=delete_failed");
}
exit;
?>