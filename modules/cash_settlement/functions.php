<?php
/**
 * Cash Settlement Module Functions
 * 
 * Contains all functions related to the cash settlement process
 */

// Include database connection
require_once __DIR__ . '/../../includes/db.php';

/**
 * Get daily cash records with filter options
 *
 * @param array $filters Filter options (staff_id, pump_id, date_from, date_to, status)
 * @param int $page Current page number
 * @param int $records_per_page Number of records per page
 * @return array Array containing records and pagination info
 */
function getCashRecords($filters = [], $page = 1, $records_per_page = 10) {
    global $conn;
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $records_per_page;
    
    // Base query
    $query = "SELECT dcr.*, 
              s.first_name, s.last_name, 
              p.pump_name, 
              u.full_name as verifier_name
              FROM daily_cash_records dcr
              LEFT JOIN staff s ON dcr.staff_id = s.staff_id
              LEFT JOIN pumps p ON dcr.pump_id = p.pump_id
              LEFT JOIN users u ON dcr.verified_by = u.user_id
              WHERE 1=1";
    
    // Add filters
    $params = [];
    $types = "";
    
    if (!empty($filters['staff_id'])) {
        $query .= " AND dcr.staff_id = ?";
        $params[] = $filters['staff_id'];
        $types .= "i";
    }
    
    if (!empty($filters['pump_id'])) {
        $query .= " AND dcr.pump_id = ?";
        $params[] = $filters['pump_id'];
        $types .= "i";
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND dcr.record_date >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND dcr.record_date <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND dcr.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['shift'])) {
        $query .= " AND dcr.shift = ?";
        $params[] = $filters['shift'];
        $types .= "s";
    }
    
    // Add ordering
    $query .= " ORDER BY dcr.record_date DESC, dcr.created_at DESC";
    
    // Count total records (for pagination)
    $count_query = str_replace("SELECT dcr.*, s.first_name, s.last_name, p.pump_name, u.full_name as verifier_name", "SELECT COUNT(*) as total", $query);
    
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_records = 0;
    if ($row = $total_result->fetch_assoc()) {
        $total_records = $row['total'] ?? 0;
    }
    $stmt->close();
    
    // Add pagination
    $query .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $records_per_page;
    $types .= "ii";
    
    // Execute the main query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    $stmt->close();
    
    // Calculate pagination info
    $total_pages = ceil($total_records / $records_per_page);
    
    return [
        'records' => $records,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page
    ];
}

/**
 * Get all active credit customers
 * 
 * @return array|false Array of credit customers or false on failure
 */
function getAllCreditCustomers() {
    global $conn;
    
    $sql = "SELECT customer_id, customer_name, current_balance, credit_limit 
            FROM credit_customers 
            WHERE status = 'active'
            ORDER BY customer_name ASC";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        return $customers;
    } catch (Exception $e) {
        error_log("Error fetching credit customers: " . $e->getMessage());
        return false;
    }
}

/**
 * Record a credit transaction for a customer
 * 
 * @param int $customer_id The credit customer ID
 * @param int $reference_id The cash record ID
 * @param float $amount The credit amount
 * @param int $staff_id The staff member who processed the transaction
 * @return bool True on success, false on failure
 */
function recordCreditTransaction($customer_id, $reference_id, $amount, $staff_id) {
    global $conn;
    
    // Include the hooks file with credit integration functions
    require_once __DIR__ . '/hooks.php';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First validate that the customer actually exists
        $check_stmt = $conn->prepare("SELECT customer_id, current_balance FROM credit_customers WHERE customer_id = ?");
        if (!$check_stmt) {
            throw new Exception("Failed to prepare customer validation statement: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $customer_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $check_stmt->close();
            throw new Exception("Credit customer ID {$customer_id} not found in database");
        }
        
        $customer_data = $result->fetch_assoc();
        $current_balance = $customer_data['current_balance'];
        $new_balance = $current_balance + $amount;
        $check_stmt->close();
        
        // First, create the sale entry to get sale_id
        // Create a dummy sale entry if it doesn't already exist
        $invoice_number = "CASH-" . $reference_id;
        $due_date = date('Y-m-d', strtotime('+30 days')); // Default 30 days due date
        $sale_id = 0;
        
        // Check if there's already a sale for this reference
        $check_sale_stmt = $conn->prepare("SELECT sale_id FROM sales WHERE reference_id = ? LIMIT 1");
        $check_sale_stmt->bind_param("i", $reference_id);
        $check_sale_stmt->execute();
        $sale_result = $check_sale_stmt->get_result();
        
        if ($sale_result->num_rows > 0) {
            // Sale already exists, use it
            $sale_id = $sale_result->fetch_assoc()['sale_id'];
            $check_sale_stmt->close();
        } else {
            $check_sale_stmt->close();
            
            // Look up the user_id from staff table
            $user_id = 0;
            $user_stmt = $conn->prepare("SELECT user_id FROM staff WHERE staff_id = ? LIMIT 1");
            if ($user_stmt) {
                $user_stmt->bind_param("i", $staff_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                if ($user_result->num_rows > 0) {
                    $user_id = $user_result->fetch_assoc()['user_id'];
                } else {
                    // If staff doesn't exist or has no user_id, use a default admin user
                    $user_id = 1;
                }
                $user_stmt->close();
            }
            
            // Create a new sale entry
            $insert_sale_sql = "INSERT INTO sales (
                invoice_number, sale_date, credit_customer_id, net_amount, 
                due_date, credit_status, reference_no, created_by, created_at,
                payment_status, sale_type, total_amount, staff_id, user_id
            ) VALUES (?, NOW(), ?, ?, ?, 'pending', ?, ?, NOW(), 'credit', 'fuel', ?, ?, ?)";
            
            $insert_sale_stmt = $conn->prepare($insert_sale_sql);
            if (!$insert_sale_stmt) {
                throw new Exception("Failed to prepare sales insert statement: " . $conn->error);
            }
            
            $insert_sale_stmt->bind_param("sidisiidii", 
                $invoice_number,
                $customer_id,
                $amount,
                $due_date,
                $reference_id,
                $staff_id,
                $amount,
                $staff_id,
                $user_id
            );
            
            if (!$insert_sale_stmt->execute()) {
                throw new Exception("Failed to insert sales record: " . $insert_sale_stmt->error);
            }
            
            $sale_id = $insert_sale_stmt->insert_id;
            $insert_sale_stmt->close();
        }

        // 1. Add transaction record with sale_id
        $sql = "INSERT INTO credit_transactions (
                    customer_id, transaction_type, amount, reference_no, 
                    transaction_date, balance_after, notes, created_by, sale_id
                ) VALUES (?, 'sale', ?, ?, NOW(), ?, 'Recorded from daily cash record', ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare transaction insert statement: " . $conn->error);
        }
        
        $stmt->bind_param("idsdii", 
            $customer_id, 
            $amount, 
            $reference_id, 
            $new_balance, 
            $staff_id,
            $sale_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert credit transaction: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to insert credit transaction - no rows affected");
        }
        
        $transaction_id = $stmt->insert_id;
        $stmt->close();
        
        // 2. Update customer balance
        $sql = "UPDATE credit_customers SET 
                    current_balance = ?,
                    updated_at = NOW()
                WHERE customer_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare customer balance update statement: " . $conn->error);
        }
        
        $stmt->bind_param("di", $new_balance, $customer_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update customer balance: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            // Double-check if customer exists (should never happen since we already validated)
            $check = $conn->query("SELECT COUNT(*) as count FROM credit_customers WHERE customer_id = {$customer_id}");
            $exists = $check->fetch_assoc()['count'];
            
            if ($exists == 0) {
                throw new Exception("Customer ID {$customer_id} not found when updating balance");
            } else {
                // If customer exists but row wasn't affected, it could be because the balance didn't change
                // which shouldn't happen with a credit sale, but log it just in case
                error_log("Warning: Customer balance not updated for ID {$customer_id} - no change in value?");
            }
        }
        $stmt->close();
        
        // 3. Create a credit sale entry in the credit_sales table using the sale_id we already created
        
        // Now create the credit_sales entry if it doesn't exist
        if ($sale_id > 0) {
            // Check if there's already a credit sale for this sale_id
            $check_credit_sale_stmt = $conn->prepare("SELECT id FROM credit_sales WHERE sale_id = ? LIMIT 1");
            $check_credit_sale_stmt->bind_param("i", $sale_id);
            $check_credit_sale_stmt->execute();
            
            if ($check_credit_sale_stmt->get_result()->num_rows == 0) {
                $check_credit_sale_stmt->close();
                
                // Create a credit_sales entry
                $insert_credit_sql = "INSERT INTO credit_sales (
                    customer_id, sale_id, credit_amount, remaining_amount, 
                    due_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
                
                $insert_credit_stmt = $conn->prepare($insert_credit_sql);
                if (!$insert_credit_stmt) {
                    throw new Exception("Failed to prepare credit_sales insert statement: " . $conn->error);
                }
                
                $insert_credit_stmt->bind_param("iidds", 
                    $customer_id,
                    $sale_id,
                    $amount,
                    $amount,
                    $due_date
                );
                
                // Ensure the customer balance is updated correctly
                if (!$conn->query("UPDATE credit_customers SET current_balance = current_balance + {$amount} WHERE customer_id = {$customer_id}")) {
                    error_log("Failed to update balance for customer ID {$customer_id}: " . $conn->error);
                }
                
                if (!$insert_credit_stmt->execute()) {
                    throw new Exception("Failed to insert credit_sales record: " . $insert_credit_stmt->error);
                }
                
                $insert_credit_stmt->close();
                error_log("Created new credit sale entry for customer {$customer_id}, sale_id {$sale_id}, amount {$amount}");
            } else {
                $check_credit_sale_stmt->close();
                error_log("Credit sale entry already exists for sale_id {$sale_id}");
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        error_log("Successfully recorded credit transaction for customer {$customer_id}, amount {$amount}, new balance {$new_balance}");
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error recording credit transaction for customer {$customer_id}, amount {$amount}: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Create a new cash record with multiple credit entries
 * 
 * @param array $data Settlement data including credit information
 * @return int|false Record ID on success, false on failure
 */
function createCashRecord($data) {
    global $conn;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Log the incoming data for debugging
        error_log("Creating cash record with data: " . print_r($data, true));
        
        // 1. Insert main cash record
        $sql = "INSERT INTO daily_cash_records (
                    record_date, staff_id, pump_id, shift, expected_amount,
                    collected_cash, collected_card, collected_credit, 
                    collected_amount, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        // Calculate total collected amount 
        $total_collected = $data['collected_cash'] + $data['collected_card'] + $data['collected_credit'];
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare daily_cash_records insert statement: " . $conn->error);
        }
        
        $stmt->bind_param(
            "siisdddds",  // 9 type specifiers for 9 parameters
            $data['record_date'],
            $data['staff_id'],
            $data['pump_id'],
            $data['shift'],
            $data['adjusted_expected_amount'],
            $data['collected_cash'],
            $data['collected_card'],
            $data['collected_credit'],
            $total_collected
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert cash record: " . $stmt->error);
        }
        
        $record_id = $stmt->insert_id;
        if (!$record_id) {
            throw new Exception("Failed to get inserted record ID");
        }
        $stmt->close();
        
        // 2. Insert record details
        $sql = "INSERT INTO cash_record_details (
                    record_id, meter_expected_amount, test_liters, 
                    fuel_price_at_time, test_value, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $test_value = $data['test_liters'] * $data['fuel_price_at_time'];
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare cash_record_details insert statement: " . $conn->error);
        }
        
        $stmt->bind_param(
            "idddd",
            $record_id,
            $data['meter_expected_amount'],
            $data['test_liters'],
            $data['fuel_price_at_time'],
            $test_value
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert cash record details: " . $stmt->error);
        }
        $stmt->close();
        
        // 3. Process credit entries if present
        if (!empty($data['credit_entries']) && is_array($data['credit_entries'])) {
    error_log("Processing " . count($data['credit_entries']) . " credit entries");
    
    foreach ($data['credit_entries'] as $entry) {
        if (empty($entry['customer_id']) || !is_numeric($entry['customer_id'])) {
            error_log("Invalid credit customer ID: " . print_r($entry, true));
            continue;
        }
        
        if (empty($entry['amount']) || !is_numeric($entry['amount']) || $entry['amount'] <= 0) {
            error_log("Invalid credit amount: " . print_r($entry, true));
            continue;
        }
        
        // Insert credit sales details
        $sql = "INSERT INTO credit_sales_details (
                    record_id, customer_id, amount, created_at
                ) VALUES (?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $customer_id = (int)$entry['customer_id'];
        $amount = (float)$entry['amount'];
        
        $stmt->bind_param("iid", $record_id, $customer_id, $amount);
        
        if (!$stmt->execute()) {
            error_log("Failed to insert credit sales detail: " . $stmt->error);
            $stmt->close();
            continue;
        }
        $stmt->close();
        
        // Create a credit sale in the credit management module
        try {
            require_once __DIR__ . '/hooks.php';
            
            createCreditSale(
                $customer_id,
                $amount,
                $record_id,
                $data['record_date'],
                $data['staff_id']
            );
            
            error_log("Successfully created credit sale for customer {$customer_id}, amount {$amount}");
        } catch (Exception $e) {
            error_log("Failed to create credit sale for customer {$customer_id}: " . $e->getMessage());
            // Don't throw exception, continue with other entries
        }
    }
}
        // Support for legacy single credit customer
        elseif ($data['collected_credit'] > 0 && !empty($data['credit_customer_id'])) {
            error_log("Processing legacy single credit customer: {$data['credit_customer_id']}, amount: {$data['collected_credit']}");
            
            // Record a single credit transaction
            recordCreditTransaction(
                $data['credit_customer_id'],
                $record_id,
                $data['collected_credit'],
                $data['staff_id']
            );
        }
        
        // Commit transaction
        $conn->commit();
        return $record_id;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error creating cash record: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Get credit entries for a specific cash record
 * 
 * @param int $record_id Cash record ID
 * @return array|false Array of credit entries or false on failure
 */
function getCreditEntriesForRecord($record_id) {
    global $conn;
    
    try {
        // First check if we have entries in the credit_sales_details table
        $sql = "SELECT csd.*, cc.customer_name
                FROM credit_sales_details csd
                JOIN credit_customers cc ON csd.customer_id = cc.customer_id
                WHERE csd.record_id = ?
                ORDER BY csd.created_at";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $entries = [];
            while ($row = $result->fetch_assoc()) {
                $entries[] = $row;
            }
            return $entries;
        }
        
        // If no entries in credit_sales_details, check legacy single credit customer
        $sql = "SELECT dcr.credit_customer_id as customer_id, 
                      dcr.collected_credit as amount,
                      cc.customer_name
                FROM daily_cash_records dcr
                JOIN credit_customers cc ON dcr.credit_customer_id = cc.customer_id
                WHERE dcr.record_id = ? AND dcr.collected_credit > 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $entries = [];
            while ($row = $result->fetch_assoc()) {
                $entries[] = $row;
            }
            return $entries;
        }
        
        // No credit entries found
        return [];
    } catch (Exception $e) {
        error_log("Error fetching credit entries: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a single cash record by ID
 *
 * @param int $record_id The record ID to retrieve
 * @return array|null The record data or null if not found
 */
function getCashRecordById($record_id) {
    global $conn;
    
    $query = "SELECT dcr.*, 
              s.first_name, s.last_name, s.staff_code,
              p.pump_name, 
              u.full_name as verifier_name
              FROM daily_cash_records dcr
              LEFT JOIN staff s ON dcr.staff_id = s.staff_id
              LEFT JOIN pumps p ON dcr.pump_id = p.pump_id
              LEFT JOIN users u ON dcr.verified_by = u.user_id
              WHERE dcr.record_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $record = $result->fetch_assoc();
    $stmt->close();
    
    if ($record) {
        // Get related adjustments
        $record['adjustments'] = getCashAdjustments($record_id);
        
        // Get record details (test liters, etc.)
        $query = "SELECT * FROM cash_record_details WHERE record_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $details_result = $stmt->get_result();
        
        if ($details_result->num_rows > 0) {
            $details = $details_result->fetch_assoc();
            // Merge details with main record
            $record = array_merge($record, $details);
        }
        $stmt->close();
        
        // Get credit entries
        $record['credit_entries'] = getCreditEntriesForRecord($record_id);
    }
    
    return $record;
}

/**
 * Get cash adjustments for a record
 *
 * @param int $record_id The record ID
 * @return array List of adjustments
 */
function getCashAdjustments($record_id) {
    global $conn;
    
    $query = "SELECT ca.*, u.full_name as approver_name
              FROM cash_adjustments ca
              LEFT JOIN users u ON ca.approved_by = u.user_id
              WHERE ca.record_id = ?
              ORDER BY ca.adjustment_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adjustments = [];
    while ($row = $result->fetch_assoc()) {
        $adjustments[] = $row;
    }
    
    $stmt->close();
    
    return $adjustments;
}

/**
 * Update a cash record
 *
 * @param int $record_id The record ID
 * @param array $data The updated data
 * @return bool Success or failure
 */
function updateCashRecord($record_id, $data) {
    global $conn;
    
    $query = "UPDATE daily_cash_records SET ";
    $params = [];
    $types = "";
    
    foreach ($data as $key => $value) {
        if ($key === 'record_id') {
            continue;
        }
        
        $query .= "$key = ?, ";
        $params[] = $value;
        
        if (is_int($value)) {
            $types .= "i";
        } elseif (is_float($value) || is_double($value)) {
            $types .= "d";
        } else {
            $types .= "s";
        }
    }
    
    // Remove trailing comma and space
    $query = rtrim($query, ", ");
    
    $query .= " WHERE record_id = ?";
    $params[] = $record_id;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Verify a cash record
 *
 * @param int $record_id The record ID
 * @param int $user_id The verifier's user ID
 * @param string $status The verification status
 * @return bool Success or failure
 */
function verifyCashRecord($record_id, $user_id, $status = 'verified') {
    global $conn;
    
    $query = "UPDATE daily_cash_records 
              SET status = ?, verified_by = ?, verification_date = NOW() 
              WHERE record_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $status, $user_id, $record_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Create a cash adjustment for a record
 *
 * @param array $data The adjustment data
 * @return int|false The new adjustment ID or false on failure
 */
function createCashAdjustment($data) {
    global $conn;
    
    $query = "INSERT INTO cash_adjustments 
              (record_id, adjustment_date, adjustment_type, amount, reason, approved_by, status, notes, created_at)
              VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $status = isset($data['status']) ? $data['status'] : 'pending';
    $notes = isset($data['notes']) ? $data['notes'] : null;
    
    $stmt->bind_param("issdiss", 
        $data['record_id'], 
        $data['adjustment_type'], 
        $data['amount'], 
        $data['reason'],
        $data['approved_by'],
        $status,
        $notes
    );
    
    if ($stmt->execute()) {
        $adjustment_id = $conn->insert_id;
        
        // Update both status fields in the daily_cash_records table
        updateCashRecord($data['record_id'], [
            'status' => 'settled',
            'settlement_status' => 'adjusted'
        ]);
        
        $stmt->close();
        return $adjustment_id;
    }
    
    $stmt->close();
    return false;
}

/**
 * Get all available staff for dropdown
 *
 * @return array List of staff
 */
function getAllStaff() {
    global $conn;
    
    // Check if the connection is valid
    if (!$conn) {
        return [];
    }
    
    $query = "SELECT staff_id, CONCAT(first_name, ' ', last_name, ' (', staff_code, ')') as full_name 
              FROM staff
              WHERE status = 'active'
              ORDER BY first_name, last_name";
    
    $result = $conn->query($query);
    
    if (!$result) {
        return [];
    }
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    return $staff;
}

/**
 * Get all active pumps for dropdown
 *
 * @return array List of pumps
 */
function getAllPumps() {
    global $conn;
    
    // Check if the connection is valid
    if (!$conn) {
        return [];
    }
    
    $query = "SELECT pump_id, pump_name 
              FROM pumps
              WHERE status = 'active'
              ORDER BY pump_name";
    
    $result = $conn->query($query);
    
    if (!$result) {
        return [];
    }
    
    $pumps = [];
    while ($row = $result->fetch_assoc()) {
        $pumps[] = $row;
    }
    
    return $pumps;
}

/**
 * Get meter readings for a specific pump, date and shift
 *
 * @param int $pump_id The pump ID
 * @param string $date The date (YYYY-MM-DD)
 * @param string $shift The shift (morning, afternoon, evening, night)
 * @return array The calculated expected amount
 */
function getPumpMeterReadings($pump_id, $date, $shift = null) {
    global $conn;
    
    // Get nozzles for this pump
    $query = "SELECT pn.nozzle_id, pn.nozzle_number, ft.fuel_name, ft.fuel_type_id
              FROM pump_nozzles pn
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              WHERE pn.pump_id = ? AND pn.status = 'active'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pump_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $nozzles = [];
    while ($row = $result->fetch_assoc()) {
        $nozzles[] = $row;
    }
    $stmt->close();
    
    if (empty($nozzles)) {
        return ['readings' => [], 'total_expected_amount' => 0];
    }
    
    // Get the current fuel prices
    $prices_query = "SELECT fp.fuel_type_id, fp.selling_price
                    FROM fuel_prices fp
                    WHERE fp.effective_date <= ? AND fp.status = 'active'
                    ORDER BY fp.fuel_type_id, fp.effective_date DESC";
    
    $stmt = $conn->prepare($prices_query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $prices_result = $stmt->get_result();
    
    $fuel_prices = [];
    // Use a temporary array to track which fuel types we've already seen
    $processed_fuel_types = [];
    
    while ($row = $prices_result->fetch_assoc()) {
        $fuel_type_id = $row['fuel_type_id'];
        // Only add the first (most recent) price we encounter for each fuel type
        if (!in_array($fuel_type_id, $processed_fuel_types)) {
            $fuel_prices[$fuel_type_id] = $row['selling_price'];
            $processed_fuel_types[] = $fuel_type_id;
        }
    }
    $stmt->close();
    
    // Get meter readings for each nozzle
    $readings = [];
    $total_expected = 0;
    $primary_fuel_price = 0; // Store primary fuel price for testing
    
    // Get currency symbol from settings
    $currency_symbol = 'LKR'; // Default
    $settings_query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
    $settings_result = $conn->query($settings_query);
    if ($settings_result && $settings_row = $settings_result->fetch_assoc()) {
        $currency_symbol = $settings_row['setting_value'];
    }
    
    foreach ($nozzles as $nozzle) {
        $query = "SELECT mr.reading_id, mr.opening_reading, mr.closing_reading, mr.volume_dispensed
                  FROM meter_readings mr
                  WHERE mr.nozzle_id = ? AND mr.reading_date = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $nozzle['nozzle_id'], $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reading = $result->fetch_assoc();
            $reading['fuel_name'] = $nozzle['fuel_name'];
            $reading['nozzle_number'] = $nozzle['nozzle_number'];
            $reading['fuel_type_id'] = $nozzle['fuel_type_id'];
            
            // Calculate expected amount based on fuel price
            $price = isset($fuel_prices[$nozzle['fuel_type_id']]) ? $fuel_prices[$nozzle['fuel_type_id']] : 0;
            $reading['unit_price'] = $price;
            $reading['expected_amount'] = $reading['volume_dispensed'] * $price;
            
            $total_expected += $reading['expected_amount'];
            $readings[] = $reading;
            
            // Set primary fuel price for this pump (use the first one found)
            if ($primary_fuel_price == 0) {
                $primary_fuel_price = $price;
            }
        }
        $stmt->close();
    }
    
    return [
        'readings' => $readings,
        'total_expected' => $total_expected,
        'currency_symbol' => $currency_symbol,
        'primary_fuel_price' => $primary_fuel_price
    ];
}

/**
 * Get allowable shortage percentage from system settings
 *
 * @return float The allowable percentage
 */
function getAllowableShortagePercentage() {
    global $conn;
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'shortage_allowance_percentage'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return floatval($row['setting_value']);
    }
    
    // Default value if setting not found
    return 0.5;
}

/**
 * Calculate allowable shortage amount based on expected amount
 *
 * @param float $expected_amount The expected amount
 * @return float The allowable shortage amount
 */
function calculateAllowableShortage($expected_amount) {
    $percentage = getAllowableShortagePercentage();
    return ($expected_amount * $percentage) / 100;
}

/**
 * Get staff assignments for a specific date
 *
 * @param string $date The date (YYYY-MM-DD)
 * @return array List of staff assignments
 */
function getStaffAssignments($date) {
    global $conn;
    
    // If connection is not valid, return empty array
    if (!$conn) {
        return [];
    }
    
    $query = "SELECT sa.*, 
              CONCAT(s.first_name, ' ', s.last_name) as staff_name,
              p.pump_name
              FROM staff_assignments sa
              JOIN staff s ON sa.staff_id = s.staff_id
              JOIN pumps p ON sa.pump_id = p.pump_id
              WHERE sa.assignment_date = ?
              ORDER BY sa.shift, p.pump_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
    }
    
    $stmt->close();
    
    return $assignments;
}

/**
 * Get dashboard summary for cash settlement
 *
 * @return array Summary statistics
 */
function getCashSettlementSummary() {
    global $conn;
    
    // Default values in case of errors
    $status_counts = [
        'pending' => 0,
        'verified' => 0,
        'settled' => 0,
        'disputed' => 0,
        'total' => 0
    ];
    
    $recent_records = [];
    $today_total = 0;
    $week_total = 0;
    $month_total = 0;
    
    // If connection is not valid, return defaults
    if (!$conn) {
        return [
            'status_counts' => $status_counts,
            'recent_records' => $recent_records,
            'today_total' => $today_total,
            'week_total' => $week_total,
            'month_total' => $month_total
        ];
    }
    
    // Get total records count by status
    $query = "SELECT status, COUNT(*) as count FROM daily_cash_records GROUP BY status";
    $result = $conn->query($query);
    
    if ($result) {
        // Reset the counter to ensure it's zero before summing
        $status_counts['total'] = 0;
        
        while ($row = $result->fetch_assoc()) {
            $status = $row['status'];
            $count = (int)$row['count'];
            
            // Add to the appropriate status
            if (isset($status_counts[$status])) {
                $status_counts[$status] = $count;
            } else {
                // Handle any unexpected status
                $status_counts[$status] = $count;
            }
            
            // Always add to total regardless of status
            $status_counts['total'] += $count;
        }
    }
    
    // Get recent records
    $query = "SELECT dcr.record_id, dcr.record_date, dcr.shift, 
              dcr.expected_amount, dcr.collected_amount, 
              (dcr.collected_amount - dcr.expected_amount) as difference, 
              dcr.status,
              CONCAT(s.first_name, ' ', s.last_name) as staff_name,
              p.pump_name
              FROM daily_cash_records dcr
              LEFT JOIN staff s ON dcr.staff_id = s.staff_id
              LEFT JOIN pumps p ON dcr.pump_id = p.pump_id
              ORDER BY dcr.record_date DESC, dcr.created_at DESC
              LIMIT 5";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_records[] = $row;
        }
    }
    
    // Get today's total collections
    $today = date('Y-m-d');
    $query = "SELECT SUM(collected_amount) as today_total FROM daily_cash_records WHERE record_date = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $today_total = $row['today_total'] ?? 0;
    }
    $stmt->close();
    
    // Get week's total collections
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    $query = "SELECT SUM(collected_amount) as week_total FROM daily_cash_records 
              WHERE record_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $week_start, $week_end);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $week_total = $row['week_total'] ?? 0;
    }
    $stmt->close();
    
    // Get month's total collections
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    
    $query = "SELECT SUM(collected_amount) as month_total FROM daily_cash_records 
              WHERE record_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $month_total = $row['month_total'] ?? 0;
    }
    $stmt->close();
    
    return [
        'status_counts' => $status_counts,
        'recent_records' => $recent_records,
        'today_total' => $today_total,
        'week_total' => $week_total,
        'month_total' => $month_total
    ];
}
/**
 * Record test liters as a tank adjustment
 * 
 * @param int $pump_id The pump ID
 * @param float $test_liters Amount of test liters
 * @param int $record_id Cash record ID for reference
 * @return bool Success status
 */
function recordTestLitersAdjustment($pump_id, $test_liters, $record_id) {
    global $conn;
    
    if ($test_liters <= 0) {
        return true; // Nothing to record
    }
    
    // Get the tank_id associated with this pump
    $stmt = $conn->prepare("SELECT tank_id FROM pumps WHERE pump_id = ?");
    $stmt->bind_param("i", $pump_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No tank found for this pump
        return false;
    }
    
    $row = $result->fetch_assoc();
    $tank_id = $row['tank_id'];
    
    // Get the current tank volume
    $stmt = $conn->prepare("SELECT current_volume FROM tanks WHERE tank_id = ?");
    $stmt->bind_param("i", $tank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Tank not found
        return false;
    }
    
    $row = $result->fetch_assoc();
    $previous_volume = $row['current_volume'];
    
    // Create notes for the operation
    $notes = "Test liters adjustment from cash settlement record #$record_id";
    
    // Include the tank management functions
    require_once '../../modules/tank_management/functions.php';
    
    // Record the adjustment operation
    return recordTankOperation(
        $conn, 
        $tank_id, 
        'adjustment', 
        $previous_volume, 
        $test_liters, // Positive amount to add back to the tank
        $notes, 
        $record_id
    );
}