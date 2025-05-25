<?php
/**
 * Cash Settlement Module - Integration Hooks
 * 
 * This file contains functions that integrate the cash settlement module with other modules
 */

require_once __DIR__ . '/../../includes/db.php';
/**
 * Get user ID from staff ID
 * 
 * @param int $staff_id The staff ID
 * @return int The user ID or 1 (default admin) if not found
 */
function getUserIdFromStaffId($staff_id) {
    global $conn;
    
    // Default admin user ID in case staff has no associated user
    $user_id = 1;
    
    // Only proceed if we have a valid staff ID
    if (!empty($staff_id) && is_numeric($staff_id)) {
        $stmt = $conn->prepare("SELECT user_id FROM staff WHERE staff_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $user_id = $result->fetch_assoc()['user_id'];
            }
            $stmt->close();
        }
    }
    
    return $user_id;
}

/**
 * Create a credit sale in the credit management module
 * 
 * This function is called after a credit transaction is recorded in the cash settlement module
 * 
 * @param int $customer_id The credit customer ID
 * @param float $amount The credit amount
 * @param string $reference_no The reference number (cash record ID)
 * @param string $record_date The date of the transaction
 * @param int $staff_id The staff member who processed the transaction
 * @return int|false The sale ID on success, or false on failure
 */
function createCreditSale($customer_id, $amount, $reference_no, $record_date, $staff_id) {
    global $conn;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Create sales record if it doesn't exist
        $invoice_number = "CASH-" . $reference_no;
        
        // Check if sales record already exists
        $check_stmt = $conn->prepare("SELECT sale_id FROM sales WHERE invoice_number = ? LIMIT 1");
        $check_stmt->bind_param("s", $invoice_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Sale already exists
            $sale_id = $result->fetch_assoc()['sale_id'];
        } else {
            // Create a new sales record
            $due_date = date('Y-m-d', strtotime('+30 days', strtotime($record_date)));
            
            // Get user_id from staff_id
            $user_id = getUserIdFromStaffId($staff_id);
            
            // Create sale record
            $insert_sale = $conn->prepare("
                INSERT INTO sales (
                    invoice_number, sale_date, credit_customer_id, net_amount, 
                    due_date, credit_status, reference_id, created_by, created_at,
                    payment_status, sale_type, total_amount, staff_id, user_id
                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), 'credit', 'fuel', ?, ?, ?)
            ");
            
            $insert_sale->bind_param("sisdisiiii", 
                $invoice_number,
                $record_date,
                $customer_id,
                $amount,
                $due_date,
                $reference_no,
                $staff_id,
                $amount,
                $staff_id,
                $user_id
            );
            
            if (!$insert_sale->execute()) {
                throw new Exception("Failed to create sales record: " . $insert_sale->error);
            }
            
            $sale_id = $conn->insert_id;
            $insert_sale->close();
        }
        
        // 2. Create credit_sales record if it doesn't exist
        $check_credit = $conn->prepare("SELECT id FROM credit_sales WHERE sale_id = ? LIMIT 1");
        $check_credit->bind_param("i", $sale_id);
        $check_credit->execute();
        
        if ($check_credit->get_result()->num_rows == 0) {
            $due_date = date('Y-m-d', strtotime('+30 days', strtotime($record_date)));
            
            $insert_credit = $conn->prepare("
                INSERT INTO credit_sales (
                    customer_id, sale_id, credit_amount, remaining_amount, 
                    due_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $insert_credit->bind_param("iidds", 
                $customer_id,
                $sale_id,
                $amount,
                $amount,
                $due_date
            );
            
            if (!$insert_credit->execute()) {
                throw new Exception("Failed to create credit sales entry: " . $insert_credit->error);
            }
            
            $insert_credit->close();
        }
        
        // 3. Update customer balance
        $update_balance = $conn->prepare("
            UPDATE credit_customers 
            SET current_balance = current_balance + ? 
            WHERE customer_id = ?
        ");
        
        $update_balance->bind_param("di", $amount, $customer_id);
        
        if (!$update_balance->execute()) {
            throw new Exception("Failed to update customer balance: " . $update_balance->error);
        }
        
        // 4. Create credit transaction if it doesn't exist
        $check_transaction = $conn->prepare("
            SELECT transaction_id FROM credit_transactions 
            WHERE reference_no = ? AND customer_id = ? LIMIT 1
        ");
        
        $check_transaction->bind_param("si", $reference_no, $customer_id);
        $check_transaction->execute();
        
        if ($check_transaction->get_result()->num_rows == 0) {
            // Get current balance
            $get_balance = $conn->prepare("SELECT current_balance FROM credit_customers WHERE customer_id = ?");
            $get_balance->bind_param("i", $customer_id);
            $get_balance->execute();
            $balance = $get_balance->get_result()->fetch_assoc()['current_balance'];
            
            // Create transaction
            $insert_transaction = $conn->prepare("
                INSERT INTO credit_transactions (
                    customer_id, sale_id, transaction_type, amount, reference_no, 
                    transaction_date, balance_after, notes, created_by
                ) VALUES (?, ?, 'sale', ?, ?, NOW(), ?, 'Credit sale from daily cash settlement', ?)
            ");
            
            $insert_transaction->bind_param("iidsdii", 
                $customer_id,
                $sale_id,
                $amount,
                $reference_no,
                $balance,
                $staff_id
            );
            
            if (!$insert_transaction->execute()) {
                throw new Exception("Failed to create credit transaction: " . $insert_transaction->error);
            }
            
            $insert_transaction->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        return $sale_id;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error creating credit sale: " . $e->getMessage());
        throw $e; // Re-throw to allow detailed error reporting
    }
}
