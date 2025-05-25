<?php
/**
 * Process Petroleum Account Transaction
 * 
 * This file handles processing new transactions for the petroleum account
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../includes/db.php';

// Include authentication
require_once '../../includes/auth.php';

// Check if user has permission to manage petroleum account
if (!has_permission('manage_petroleum_account')) {
    $_SESSION['flash_message'] = "You do not have permission to manage petroleum account transactions.";
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Invalid request method.";
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php');
    exit;
}

// Initialize variables
$transaction_type = $_POST['transaction_type'] ?? '';
$amount = $_POST['amount'] ?? '';
$transaction_date = $_POST['transaction_date'] ?? '';
$reference_no = $_POST['reference_no'] ?? '';
$reference_type = $_POST['reference_type'] ?? '';
$description = $_POST['description'] ?? '';

// Validate inputs
$errors = [];

if (empty($transaction_type) || !in_array($transaction_type, ['deposit', 'withdrawal', 'adjustment'])) {
    $errors[] = "Invalid transaction type.";
}

if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
    $errors[] = "Please enter a valid amount greater than zero.";
}

if (empty($transaction_date)) {
    $errors[] = "Please select a transaction date.";
}

// If there are errors, redirect back with error messages
if (!empty($errors)) {
    $_SESSION['flash_message'] = implode('<br>', $errors);
    $_SESSION['flash_type'] = 'error';
    $_SESSION['form_data'] = $_POST; // Save form data for repopulation
    header('Location: add_transaction.php?type=' . urlencode($transaction_type));
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Determine if transaction needs approval
    $status = has_permission('approve_petroleum_account') ? 'completed' : 'pending';
    
    // Get current balance
    $current_balance = 0;
    if ($status === 'completed') {
        $query = "SELECT balance_after FROM petroleum_account_transactions 
                  WHERE status = 'completed' 
                  ORDER BY transaction_id DESC LIMIT 1";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_balance = $row['balance_after'];
        }
    }
    
    // Calculate new balance
    $balance_after = $current_balance;
    if ($transaction_type === 'deposit') {
        $balance_after += $amount;
    } elseif ($transaction_type === 'withdrawal') {
        $balance_after -= $amount;
        
        // Check if withdrawal would result in negative balance
        if ($balance_after < 0 && $status === 'completed') {
            throw new Exception("This withdrawal would result in a negative balance. Current balance: " . format_currency($conn, $current_balance));
        }
    } elseif ($transaction_type === 'adjustment') {
        // For adjustment, we set the balance directly to the amount
        $balance_after = $amount;
    }
    
    // Insert transaction
    $sql = "INSERT INTO petroleum_account_transactions (
                transaction_date, 
                transaction_type, 
                amount, 
                balance_after, 
                reference_no, 
                reference_type, 
                description, 
                status, 
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssddssssi",
        $transaction_date,
        $transaction_type,
        $amount,
        $balance_after,
        $reference_no,
        $reference_type,
        $description,
        $status,
        $_SESSION['user_id']
    );
    
    $stmt->execute();
    $transaction_id = $stmt->insert_id;
    $stmt->close();
    
    // Handle file upload if exists
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/receipts/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $file_path)) {
            // Update transaction with receipt image path
            $sql = "UPDATE petroleum_account_transactions SET receipt_image = ? WHERE transaction_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $filename, $transaction_id);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Failed to upload receipt image.");
        }
    }
    
    // Check if this is a deposit for a pending top-up
    if ($transaction_type === 'deposit' && $reference_type === 'purchase_order' && !empty($reference_no) && $status === 'completed') {
        // Get purchase order ID from the reference number
        $sql = "SELECT po_id FROM purchase_orders WHERE po_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $reference_no);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $po_id = $row['po_id'];
            
            // Check if there's a pending top-up for this PO
            $sql = "SELECT topup_id FROM pending_topups WHERE po_id = ? AND status = 'pending'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $po_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $topup_id = $row['topup_id'];
                
                // Update the top-up as completed
                $sql = "UPDATE pending_topups SET status = 'completed', completed_at = NOW() WHERE topup_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $topup_id);
                $stmt->execute();
                
                // Update the purchase order status
                $sql = "UPDATE purchase_orders SET account_check_status = 'sufficient' WHERE po_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $po_id);
                $stmt->execute();
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $message = ucfirst($transaction_type) . " transaction recorded successfully.";
    
    // If the status is pending, add additional message
    if ($status === 'pending') {
        $message .= " The transaction requires approval before it will affect the account balance.";
    }
    
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = 'success';
    
    // Redirect to appropriate page
    header('Location: transactions.php');
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_message'] = "Error processing transaction: " . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    $_SESSION['form_data'] = $_POST; // Save form data for repopulation
    header('Location: add_transaction.php?type=' . urlencode($transaction_type));
    exit;
}

/**
 * Format currency with the system setting
 * 
 * @param mysqli $conn Database connection
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function format_currency($conn, $amount) {
    $currency_symbol = '';
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currency_symbol = $row['setting_value'];
    }
    
    return $currency_symbol . ' ' . number_format($amount, 2);
}
?>