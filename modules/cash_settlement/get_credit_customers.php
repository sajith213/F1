<?php
/**
 * API Endpoint: Get Credit Customers
 * 
 * Returns a list of active credit customers with their credit limits and balances
 */

// Include necessary files
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once 'functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!is_logged_in() || !has_permission('manage_cash')) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get credit customers
$customers = getAllCreditCustomers();

if ($customers === false) {
    echo json_encode(['error' => 'Failed to fetch credit customers']);
    exit;
}

// Return customers as JSON
echo json_encode(['customers' => $customers]);