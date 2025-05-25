<?php
/**
 * Petroleum Account - Balance Check Functions
 * 
 * Functions for checking petroleum account balance and creating transactions
 * for purchase orders.
 */

/**
 * Check if the petroleum account has sufficient balance for a purchase order
 * 
 * @param mysqli $conn Database connection
 * @param float $amount Amount needed for the purchase order
 * @return array Array with 'sufficient' status (boolean) and 'current_balance'
 */
function check_account_balance($conn, $amount) {
    // Get current account balance (latest transaction balance_after)
    $current_balance = 0;
    $query = "SELECT balance_after FROM petroleum_account_transactions 
              WHERE status = 'completed' 
              ORDER BY transaction_id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_balance = $row['balance_after'];
    }
    
    // Get minimum required balance from settings
    $min_balance = 0;
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'min_account_balance'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $min_balance = floatval($row['setting_value']);
    }
    
    // Check if balance is sufficient (amount + minimum required balance)
    $is_sufficient = ($current_balance >= ($amount + $min_balance));
    
    return [
        'sufficient' => $is_sufficient,
        'current_balance' => $current_balance,
        'min_balance' => $min_balance,
        'required_amount' => $amount + $min_balance
    ];
}

/**
 * Create a withdrawal transaction for a purchase order payment
 * 
 * @param mysqli $conn Database connection
 * @param int $po_id Purchase order ID
 * @param float $amount Amount to withdraw
 * @param string $po_number Purchase order number for reference
 * @param string $custom_description Optional custom description for the transaction
 * @return bool True if transaction created successfully, false otherwise
 */
function create_po_withdrawal($conn, $po_id, $amount, $po_number, $custom_description = '') {
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Get current account balance
        $balance_check = check_account_balance($conn, $amount);
        $current_balance = $balance_check['current_balance'];
        
        // If there's not enough balance, return false
        if (!$balance_check['sufficient']) {
            // Log the insufficient balance warning
            error_log("Petroleum Account Warning: Insufficient balance for withdrawal. PO ID: $po_id, Amount: $amount, Current Balance: $current_balance");
            $conn->rollback();
            return false;
        }
        
        // Calculate new balance
        $new_balance = $current_balance - $amount;
        
        // Create description
        $description = empty($custom_description) ? "Payment for purchase order $po_number" : $custom_description;
        
        // Insert the withdrawal transaction
        $query = "INSERT INTO petroleum_account_transactions 
                  (transaction_date, transaction_type, amount, balance_after, 
                   reference_no, reference_type, description, status, created_by) 
                  VALUES (NOW(), 'withdrawal', ?, ?, ?, 'purchase_order', ?, 'completed', ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        // Get current user ID from session
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        $stmt->bind_param("ddssi", $amount, $new_balance, $po_number, $description, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        // Update purchase order with account check status
        $update_query = "UPDATE purchase_orders 
                         SET account_check_status = 'sufficient' 
                         WHERE po_id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param("i", $po_id);
            $stmt->execute();
        }
        
        // Commit the transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Petroleum Account Error: " . $e->getMessage());
        return false;
    }
}/**
 * Process partial payment from petroleum account
 * 
 * @param mysqli $conn Database connection
 * @param int $po_id Purchase order ID
 * @param float $amount Total amount needed
 * @param string $po_number Purchase order number
 * @param string $description Transaction description
 * @return array Status of the transaction with details
 */
function process_partial_payment($conn, $po_id, $amount, $po_number, $description) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get current balance
        $current_balance = 0;
        $query = "SELECT balance_after FROM petroleum_account_transactions 
                  WHERE status = 'completed' 
                  ORDER BY transaction_id DESC LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_balance = $row['balance_after'];
        }
        
        // Get minimum required balance
        $min_balance = 0;
        $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'min_account_balance'";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $min_balance = floatval($row['setting_value']);
        }
        
        // Calculate available balance for payment
        $available_for_payment = max(0, $current_balance - $min_balance);
        
        // If no balance is available, create pending topup for full amount
        if ($available_for_payment <= 0) {
            // Create pending topup for full amount
            $deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $query = "INSERT INTO pending_topups 
                      (po_id, required_amount, deadline, status, created_at) 
                      VALUES (?, ?, ?, 'pending', NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ids", $po_id, $amount, $deadline);
            $stmt->execute();
            
            // Update purchase order account check status
            $update_query = "UPDATE purchase_orders 
                           SET account_check_status = 'insufficient', 
                               topup_deadline = ? 
                           WHERE po_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $deadline, $po_id);
            $stmt->execute();
            
            $conn->commit();
            
            return [
                'status' => 'pending',
                'deducted' => 0,
                'remaining' => $amount,
                'message' => 'No balance available in petroleum account. Full amount added to pending topups.'
            ];
        }
        
        // Determine how much to deduct and how much to add to pending topups
        $amount_to_deduct = min($available_for_payment, $amount);
        $amount_pending = $amount - $amount_to_deduct;
        
        // If we can deduct some amount, do it
        if ($amount_to_deduct > 0) {
            // Calculate new balance
            $new_balance = $current_balance - $amount_to_deduct;
            
            // Insert withdrawal transaction
            $query = "INSERT INTO petroleum_account_transactions 
                      (transaction_date, transaction_type, amount, balance_after, 
                       reference_no, reference_type, description, status, created_by) 
                      VALUES (NOW(), 'withdrawal', ?, ?, ?, 'purchase_order', ?, 'completed', ?)";
            
            $stmt = $conn->prepare($query);
            
            // Get current user ID from session
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            $partial_desc = $description . " (Partial payment)";
            $stmt->bind_param("ddssi", $amount_to_deduct, $new_balance, $po_number, $partial_desc, $user_id);
            $stmt->execute();
        }
        
        // If there's still remaining amount, create pending topup
        if ($amount_pending > 0) {
            // Create pending topup for remaining amount
            $deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $query = "INSERT INTO pending_topups 
                      (po_id, required_amount, deadline, status, created_at) 
                      VALUES (?, ?, ?, 'pending', NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ids", $po_id, $amount_pending, $deadline);
            $stmt->execute();
            
            // Update purchase order account check status
            $update_query = "UPDATE purchase_orders 
                           SET account_check_status = 'insufficient', 
                               topup_deadline = ? 
                           WHERE po_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $deadline, $po_id);
            $stmt->execute();
            
            $status = 'partial';
            $message = 'Partial amount deducted from petroleum account. Remaining amount added to pending topups.';
        } else {
            // Full amount deducted
            $update_query = "UPDATE purchase_orders 
                           SET account_check_status = 'sufficient'
                           WHERE po_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $po_id);
            $stmt->execute();
            
            $status = 'complete';
            $message = 'Full amount deducted from petroleum account.';
        }
        
        $conn->commit();
        
        return [
            'status' => $status,
            'deducted' => $amount_to_deduct,
            'remaining' => $amount_pending,
            'message' => $message
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Petroleum Account Error: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'deducted' => 0,
            'remaining' => $amount,
            'message' => 'Error processing payment: ' . $e->getMessage()
        ];
    }
}