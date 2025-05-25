<?php
/**
 * Credit Synchronization Test Script
 * 
 * This script checks and fixes issues with credit sales not showing up in credit management module
 */

// Include required files
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Credit Sales Synchronization Tool</h1>";

// Function to check if a table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    return ($result && $result->num_rows > 0);
}

// Function to display credit sales
function displayCreditSales($conn, $customerId = null) {
    echo "<h2>Credit Sales</h2>";
    
    $where = "";
    if ($customerId) {
        $where = "WHERE cs.customer_id = " . intval($customerId);
    }
    
    $query = "
        SELECT cs.*, cc.customer_name, s.invoice_number, s.sale_date, s.net_amount
        FROM credit_sales cs
        JOIN credit_customers cc ON cs.customer_id = cc.customer_id
        JOIN sales s ON cs.sale_id = s.sale_id
        {$where}
        ORDER BY s.sale_date DESC
        LIMIT 20
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p>Error querying credit sales: " . $conn->error . "</p>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p>No credit sales found.</p>";
        return;
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Customer</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Status</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['customer_name']}</td>";
        echo "<td>{$row['invoice_number']}</td>";
        echo "<td>{$row['sale_date']}</td>";
        echo "<td>{$row['credit_amount']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Function to display credit transactions
function displayCreditTransactions($conn, $customerId = null) {
    echo "<h2>Credit Transactions</h2>";
    
    $where = "";
    if ($customerId) {
        $where = "WHERE ct.customer_id = " . intval($customerId);
    }
    
    $query = "
        SELECT ct.*, cc.customer_name, s.invoice_number, s.sale_date
        FROM credit_transactions ct
        JOIN credit_customers cc ON ct.customer_id = cc.customer_id
        LEFT JOIN sales s ON ct.sale_id = s.sale_id
        {$where}
        ORDER BY ct.transaction_date DESC
        LIMIT 20
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p>Error querying credit transactions: " . $conn->error . "</p>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p>No credit transactions found.</p>";
        return;
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Customer</th><th>Type</th><th>Invoice</th><th>Amount</th><th>Reference</th><th>Date</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['transaction_id']}</td>";
        echo "<td>{$row['customer_name']}</td>";
        echo "<td>{$row['transaction_type']}</td>";
        echo "<td>{$row['invoice_number']}</td>";
        echo "<td>{$row['amount']}</td>";
        echo "<td>{$row['reference_no']}</td>";
        echo "<td>{$row['transaction_date']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Function to display credit sales details
function displayCreditSalesDetails($conn) {
    echo "<h2>Credit Sales Details</h2>";
    
    $query = "
        SELECT csd.*, dcr.record_date, cc.customer_name
        FROM credit_sales_details csd
        JOIN credit_customers cc ON csd.customer_id = cc.customer_id
        JOIN daily_cash_records dcr ON csd.record_id = dcr.record_id
        ORDER BY csd.created_at DESC
        LIMIT 20
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p>Error querying credit sales details: " . $conn->error . "</p>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p>No credit sales details found.</p>";
        return;
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Record ID</th><th>Date</th><th>Customer</th><th>Amount</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['record_id']}</td>";
        echo "<td>{$row['record_date']}</td>";
        echo "<td>{$row['customer_name']}</td>";
        echo "<td>{$row['amount']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Function to display daily cash records with credit
function displayCashRecords($conn) {
    echo "<h2>Daily Cash Records with Credit</h2>";
    
    $query = "
        SELECT dcr.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name, 
               cc.customer_name as credit_customer_name
        FROM daily_cash_records dcr
        JOIN staff s ON dcr.staff_id = s.staff_id 
        LEFT JOIN credit_customers cc ON dcr.credit_customer_id = cc.customer_id
        WHERE dcr.collected_credit > 0
        ORDER BY dcr.record_date DESC
        LIMIT 20
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p>Error querying cash records: " . $conn->error . "</p>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p>No cash records with credit found.</p>";
        return;
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Date</th><th>Staff</th><th>Credit Amount</th><th>Actions</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['record_id']}</td>";
        echo "<td>{$row['record_date']}</td>";
        echo "<td>{$row['staff_name']}</td>";
        echo "<td>{$row['collected_credit']}</td>";
        echo "<td>
                <a href='?fix=1&record_id={$row['record_id']}' style='color:blue;'>Fix Record</a>
              </td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Function to fix a specific cash record
function fixCashRecord($conn, $recordId) {
    echo "<h2>Fixing Cash Record #{$recordId}</h2>";
    
    // First, check if this record exists and has credit amount
    $stmt = $conn->prepare("
        SELECT dcr.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name, s.staff_id, s.user_id
        FROM daily_cash_records dcr
        JOIN staff s ON dcr.staff_id = s.staff_id 
        WHERE dcr.record_id = ? AND dcr.collected_credit > 0
    ");
    
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p>Error: Cash record #{$recordId} not found or has no credit amount.</p>";
        return;
    }
    
    $cashRecord = $result->fetch_assoc();
    $stmt->close();
    
    echo "<p>Found cash record with credit amount: {$cashRecord['collected_credit']}</p>";
    
    // Check if there are credit entries for this record
    $stmt = $conn->prepare("
        SELECT csd.*, cc.customer_name
        FROM credit_sales_details csd
        JOIN credit_customers cc ON csd.customer_id = cc.customer_id  
        WHERE csd.record_id = ?
    ");
    
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    $creditEntries = [];
    
    while ($row = $result->fetch_assoc()) {
        $creditEntries[] = $row;
    }
    $stmt->close();
    
    if (empty($creditEntries)) {
        echo "<p>No credit entries found for this record. Will check for legacy single credit customer...</p>";
        
        // Check for legacy single credit customer
        $stmt = $conn->prepare("
            SELECT credit_customer_id 
            FROM daily_cash_records 
            WHERE record_id = ? AND credit_customer_id IS NOT NULL
        ");
        
        $stmt->bind_param("i", $recordId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $customerId = $row['credit_customer_id'];
            
            echo "<p>Found legacy single credit customer ID: {$customerId}</p>";
            
            // Create a new entry
            $creditEntries[] = [
                'customer_id' => $customerId,
                'amount' => $cashRecord['collected_credit']
            ];
        } else {
            echo "<p>Error: No credit customer found for this record.</p>";
            return;
        }
        $stmt->close();
    } else {
        echo "<p>Found " . count($creditEntries) . " credit entries for this record.</p>";
    }
    
    // Process each credit entry
    foreach ($creditEntries as $entry) {
        echo "<p>Processing credit entry for customer ID: {$entry['customer_id']}, amount: {$entry['amount']}</p>";
        
        // Check if sales record exists - using a more reliable approach
        $stmt = $conn->prepare("
            SELECT s.sale_id 
            FROM sales s 
            LEFT JOIN credit_transactions ct ON s.sale_id = ct.sale_id
            WHERE (s.invoice_number = ? OR ct.reference_no = ?)
            LIMIT 1
        ");
        $invoice_number = "CASH-" . $recordId;
        $stmt->bind_param("ss", $invoice_number, $recordId);
        $stmt->execute();
        $result = $stmt->get_result();
        $saleId = 0;
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $saleId = $row['sale_id'];
            echo "<p>Found existing sale record ID: {$saleId}</p>";
        } else {
            // Create a sales record
            echo "<p>Creating new sales record...</p>";
            
            // Get user_id from staff_id
            $userId = 1; // Default admin user
            $stmt2 = $conn->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
            $stmt2->bind_param("i", $cashRecord['staff_id']);
            $stmt2->execute();
            $userResult = $stmt2->get_result();
            
            if ($userResult->num_rows > 0) {
                $userId = $userResult->fetch_assoc()['user_id'];
            }
            $stmt2->close();
            
            $invoiceNumber = "CASH-" . $recordId;
            $dueDate = date('Y-m-d', strtotime('+30 days', strtotime($cashRecord['record_date'])));
            
            $stmt2 = $conn->prepare("
                INSERT INTO sales (
                    invoice_number, sale_date, credit_customer_id, net_amount, 
                    due_date, credit_status, reference_no, created_by, created_at,
                    payment_status, sale_type, total_amount, staff_id, user_id
                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), 'credit', 'fuel', ?, ?, ?)
            ");
            
            $stmt2->bind_param("sisdisiiii", 
                $invoiceNumber,
                $cashRecord['record_date'],
                $entry['customer_id'],
                $entry['amount'],
                $dueDate,
                $recordId,
                $cashRecord['staff_id'],
                $entry['amount'],
                $cashRecord['staff_id'],
                $userId
            );
            
            if ($stmt2->execute()) {
                $saleId = $conn->insert_id;
                echo "<p>Created new sale record ID: {$saleId}</p>";
            } else {
                echo "<p>Error creating sale record: " . $stmt2->error . "</p>";
            }
            $stmt2->close();
        }
        
        // If we have a sale ID, check if there's a credit_sale entry
        if ($saleId > 0) {
            $stmt2 = $conn->prepare("SELECT id FROM credit_sales WHERE sale_id = ?");
            $stmt2->bind_param("i", $saleId);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows === 0) {
                // Create credit_sales entry
                echo "<p>Creating new credit_sales entry...</p>";
                
                $dueDate = date('Y-m-d', strtotime('+30 days', strtotime($cashRecord['record_date'])));
                
                $stmt3 = $conn->prepare("
                    INSERT INTO credit_sales (
                        customer_id, sale_id, credit_amount, remaining_amount, 
                        due_date, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt3->bind_param("iidds", 
                    $entry['customer_id'],
                    $saleId,
                    $entry['amount'],
                    $entry['amount'],
                    $dueDate
                );
                
                if ($stmt3->execute()) {
                    echo "<p>Created new credit_sales entry</p>";
                } else {
                    echo "<p>Error creating credit_sales entry: " . $stmt3->error . "</p>";
                }
                $stmt3->close();
            } else {
                echo "<p>Credit sales entry already exists</p>";
            }
            $stmt2->close();
            
            // Check if the credit transaction has sale_id
            $stmt2 = $conn->prepare("
                SELECT transaction_id FROM credit_transactions 
                WHERE reference_no = ? AND customer_id = ? AND transaction_type = 'sale'
            ");
            $stmt2->bind_param("ii", $recordId, $entry['customer_id']);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $transactionId = $result2->fetch_assoc()['transaction_id'];
                
                // Update the transaction to include sale_id
                $stmt3 = $conn->prepare("UPDATE credit_transactions SET sale_id = ? WHERE transaction_id = ?");
                $stmt3->bind_param("ii", $saleId, $transactionId);
                
                if ($stmt3->execute()) {
                    echo "<p>Updated credit transaction #{$transactionId} with sale_id: {$saleId}</p>";
                } else {
                    echo "<p>Error updating credit transaction: " . $stmt3->error . "</p>";
                }
                $stmt3->close();
            } else {
                echo "<p>No matching credit transaction found</p>";
            }
            $stmt2->close();
        }
    }
    
    echo "<p>Fix complete for cash record #{$recordId}</p>";
}

// Check if tables exist
echo "<h2>Database Check</h2>";
echo "<p>Checking if required tables exist...</p>";

$requiredTables = ['credit_customers', 'credit_sales', 'credit_transactions', 'sales', 'daily_cash_records', 'credit_sales_details'];
$missingTables = [];

foreach ($requiredTables as $table) {
    if (!tableExists($conn, $table)) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "<p>Warning: The following tables are missing: " . implode(', ', $missingTables) . "</p>";
} else {
    echo "<p>All required tables exist.</p>";
}

// Display credit sales details
displayCreditSalesDetails($conn);

// First, get a list of all credit customers with balances
echo "<h2>Credit Customers</h2>";
$query = "SELECT * FROM credit_customers ORDER BY customer_name";
$result = $conn->query($query);

if (!$result) {
    echo "<p>Error querying credit customers: " . $conn->error . "</p>";
} else {
    if ($result->num_rows == 0) {
        echo "<p>No credit customers with balances found.</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Balance</th><th>Credit Limit</th><th>Actions</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['customer_id']}</td>";
            echo "<td>{$row['customer_name']}</td>";
            echo "<td>{$row['current_balance']}</td>";
            echo "<td>{$row['credit_limit']}</td>";
            echo "<td>
                    <a href='?customer_id={$row['customer_id']}' style='color:blue;'>View Details</a>
                  </td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
}

// Check if we need to display customer details
if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    $customerId = intval($_GET['customer_id']);
    echo "<h2>Details for Customer ID: {$customerId}</h2>";
    
    // Display customer info
    $stmt = $conn->prepare("SELECT * FROM credit_customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        echo "<p><strong>Name:</strong> {$customer['customer_name']}</p>";
        echo "<p><strong>Phone:</strong> {$customer['phone_number']}</p>";
        echo "<p><strong>Balance:</strong> {$customer['current_balance']}</p>";
        echo "<p><strong>Credit Limit:</strong> {$customer['credit_limit']}</p>";
        
        // Display credit transactions
        displayCreditTransactions($conn, $customerId);
        
        // Display credit sales
        displayCreditSales($conn, $customerId);
    } else {
        echo "<p>Customer not found.</p>";
    }
    $stmt->close();
} else if (isset($_GET['fix']) && isset($_GET['record_id']) && is_numeric($_GET['record_id'])) {
    // Fix a specific cash record
    fixCashRecord($conn, intval($_GET['record_id']));
} else {
    // Display all credit sales
    displayCreditSales($conn);
    
    // Display all credit transactions 
    displayCreditTransactions($conn);
    
    // Display cash records with credit
    displayCashRecords($conn);
}
?>
