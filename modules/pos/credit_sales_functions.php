<?php
/**
 * Credit Sales Functions
 * 
 * Functions for handling credit sales in the POS system
 */

/**
 * Get all active credit customers
 * 
 * @param mysqli $conn Database connection
 * @return array Array of credit customers
 */
function getCreditCustomersData($conn) {
    $customers = [];
    
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'sale' THEN t.amount ELSE 0 END), 0) as total_sales,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) as total_payments
        FROM credit_customers c
        LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
        WHERE c.status = 'active'
        GROUP BY c.customer_id
        ORDER BY c.customer_name ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        $stmt->close();
    }
    
    return $customers;
}

/**
 * Get credit customer by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $customerId Customer ID
 * @return array|null Customer data or null if not found
 */
function getCreditCustomerById($conn, $customerId) {
    $stmt = $conn->prepare("
        SELECT * FROM credit_customers
        WHERE customer_id = ? AND status = 'active'
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row;
        }
        
        $stmt->close();
    }
    
    return null;
}

/**
 * Process a credit sale
 * 
 * @param mysqli $conn Database connection
 * @param int $customerId Customer ID
 * @param int $saleId Sale ID
 * @param float $amount Sale amount
 * @param string $dueDate Due date for payment
 * @param int $createdBy User ID who created the sale
 * @return bool True if successful, false otherwise
 */
function processCreditSale($conn, $customerId, $saleId, $amount, $dueDate, $createdBy) {
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Get current customer balance
        $stmt = $conn->prepare("SELECT current_balance, credit_limit FROM credit_customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Customer not found");
        }
        
        $customer = $result->fetch_assoc();
        $currentBalance = $customer['current_balance'];
        $creditLimit = $customer['credit_limit'];
        
        // Check if sale would exceed credit limit
        $newBalance = $currentBalance + $amount;
        if ($newBalance > $creditLimit) {
            throw new Exception("This sale would exceed the customer's credit limit of " . 
                number_format($creditLimit, 2));
        }
        
        // Update the sales record
        $stmt = $conn->prepare("
            UPDATE sales 
            SET credit_customer_id = ?, credit_status = 'pending', due_date = ?
            WHERE sale_id = ?
        ");
        $stmt->bind_param("isi", $customerId, $dueDate, $saleId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update sale: " . $stmt->error);
        }
        $stmt->close();
        
        // Create a credit transaction
        $stmt = $conn->prepare("
            INSERT INTO credit_transactions (
                customer_id, sale_id, transaction_date, amount, 
                transaction_type, balance_after, reference_no, created_by
            ) VALUES (?, ?, NOW(), ?, 'sale', ?, ?, ?)
        ");
        
        $reference = "INV-" . $saleId;
        $stmt->bind_param("iiddsi", $customerId, $saleId, $amount, $newBalance, $reference, $createdBy);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create transaction: " . $stmt->error);
        }
        $stmt->close();
        
        // Update customer balance
        $stmt = $conn->prepare("
            UPDATE credit_customers 
            SET current_balance = current_balance + ?
            WHERE customer_id = ?
        ");
        $stmt->bind_param("di", $amount, $customerId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update customer balance: " . $stmt->error);
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Credit sale processing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get default due date (usually 30 days from today)
 * 
 * @return string Due date in Y-m-d format
 */
function getDefaultDueDate() {
    return date('Y-m-d', strtotime('+30 days'));
}

/**
 * Check if a customer has available credit
 * 
 * @param array $customer Customer data
 * @param float $amount Amount to check
 * @return bool True if credit is available, false otherwise
 */
function hasAvailableCredit($customer, $amount) {
    $availableCredit = $customer['credit_limit'] - $customer['current_balance'];
    return $availableCredit >= $amount;
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @param string $symbol Currency symbol
 * @return string Formatted amount with currency symbol
 */
function formatCreditAmount($amount, $symbol = '$') {
    return $symbol . ' ' . number_format($amount, 2);
}