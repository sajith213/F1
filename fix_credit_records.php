<?php
/**
 * Credit Records Fix Script
 * 
 * This script repairs credit records by properly linking credit sales to the credit management module
 */

// Include required files
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session but don't require authentication for this tool
session_start();
// Disable authentication for this diagnostic tool
// if (!isset($_SESSION['user_id'])) {
//     echo "<h2>Authentication Required</h2>";
//     echo "<p>Please <a href='login.php'>login</a> to use this tool.</p>";
//     exit;
// }

// Function to get all credit cash records
function getAllCreditCashRecords($conn) {
    $query = "
        SELECT dcr.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name
        FROM daily_cash_records dcr
        JOIN staff s ON dcr.staff_id = s.staff_id 
        WHERE dcr.collected_credit > 0
        ORDER BY dcr.record_date DESC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        return [];
    }
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    return $records;
}

// Function to get credit entries for a record
function getCreditEntriesForRecord($conn, $recordId) {
    // Check if we have entries in the credit_sales_details table
    $sql = "SELECT csd.*, cc.customer_name
            FROM credit_sales_details csd
            JOIN credit_customers cc ON csd.customer_id = cc.customer_id
            WHERE csd.record_id = ?
            ORDER BY csd.created_at";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recordId);
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
    $stmt->bind_param("i", $recordId);
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
}

// Function to fix a credit record
function fixCreditRecord($conn, $recordId) {
    $messages = [];
    
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
        $messages[] = "Error: Cash record #{$recordId} not found or has no credit amount.";
        return [
            'success' => false,
            'messages' => $messages
        ];
    }
    
    $cashRecord = $result->fetch_assoc();
    $stmt->close();
    
    $messages[] = "Found cash record with credit amount: {$cashRecord['collected_credit']}";
    
    // Get credit entries for this record
    $creditEntries = getCreditEntriesForRecord($conn, $recordId);
    
    if (empty($creditEntries)) {
        $messages[] = "No credit entries found for this record. Cannot proceed.";
        return [
            'success' => false,
            'messages' => $messages
        ];
    }
    
    $messages[] = "Found " . count($creditEntries) . " credit entries for this record.";
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Process each credit entry
        foreach ($creditEntries as $entry) {
            $messages[] = "Processing credit entry for customer ID: {$entry['customer_id']}, amount: {$entry['amount']}";
            
            // Check if sales record exists
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
                $messages[] = "Found existing sale record ID: {$saleId}";
            } else {
                // Create a sales record
                $messages[] = "Creating new sales record...";
                
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
                    $messages[] = "Created new sale record ID: {$saleId}";
                } else {
                    $messages[] = "Error creating sale record: " . $stmt2->error;
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
                    $messages[] = "Creating new credit_sales entry...";
                    
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
                        $messages[] = "Created new credit_sales entry";
                        
                        // Update customer balance
                        $stmt4 = $conn->prepare("
                            UPDATE credit_customers 
                            SET current_balance = current_balance + ? 
                            WHERE customer_id = ?
                        ");
                        
                        $stmt4->bind_param("di", $entry['amount'], $entry['customer_id']);
                        
                        if ($stmt4->execute()) {
                            $messages[] = "Updated customer balance";
                        } else {
                            $messages[] = "Error updating customer balance: " . $stmt4->error;
                        }
                        $stmt4->close();
                        
                    } else {
                        $messages[] = "Error creating credit_sales entry: " . $stmt3->error;
                    }
                    $stmt3->close();
                } else {
                    $messages[] = "Credit sales entry already exists";
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
                        $messages[] = "Updated credit transaction #{$transactionId} with sale_id: {$saleId}";
                    } else {
                        $messages[] = "Error updating credit transaction: " . $stmt3->error;
                    }
                    $stmt3->close();
                } else {
                    // Create a new transaction
                    $stmt3 = $conn->prepare("
                        INSERT INTO credit_transactions (
                            customer_id, transaction_type, amount, reference_no, 
                            transaction_date, balance_after, notes, created_by, sale_id
                        ) VALUES (?, 'sale', ?, ?, NOW(), ?, 'Recorded from fix script', ?, ?)
                    ");
                    
                    // Get current balance
                    $balance = 0;
                    $stmt4 = $conn->prepare("SELECT current_balance FROM credit_customers WHERE customer_id = ?");
                    $stmt4->bind_param("i", $entry['customer_id']);
                    $stmt4->execute();
                    $result4 = $stmt4->get_result();
                    if ($row4 = $result4->fetch_assoc()) {
                        $balance = $row4['current_balance'];
                    }
                    $stmt4->close();
                    
                    $stmt3->bind_param("idsdii", 
                        $entry['customer_id'],
                        $entry['amount'],
                        $recordId,
                        $balance,
                        $cashRecord['staff_id'],
                        $saleId
                    );
                    
                    if ($stmt3->execute()) {
                        $messages[] = "Created new credit transaction";
                    } else {
                        $messages[] = "Error creating credit transaction: " . $stmt3->error;
                    }
                    $stmt3->close();
                }
                $stmt2->close();
            }
        }
        
        // Commit changes
        $conn->commit();
        
        $messages[] = "Successfully fixed credit record #{$recordId}";
        return [
            'success' => true,
            'messages' => $messages
        ];
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        $messages[] = "Error: " . $e->getMessage();
        return [
            'success' => false,
            'messages' => $messages
        ];
    }
}

// Process fix request
$fixResult = null;
if (isset($_GET['fix']) && isset($_GET['record_id']) && is_numeric($_GET['record_id'])) {
    $recordId = intval($_GET['record_id']);
    $fixResult = fixCreditRecord($conn, $recordId);
}

// Get all credit records
$creditRecords = getAllCreditCashRecords($conn);

// Get currency symbol
$currency_symbol = 'LKR'; // Default
$settings_query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$settings_result = $conn->query($settings_query);
if ($settings_result && $settings_row = $settings_result->fetch_assoc()) {
    $currency_symbol = $settings_row['setting_value'];
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Records Fix Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Credit Records Fix Tool</h1>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-home mr-2"></i> Dashboard
            </a>
        </div>
        
        <!-- Explanation Panel -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        This tool fixes credit records by ensuring that all cash settlement credits are properly linked
                        to the credit management module. Use this if you notice credit sales not appearing in the
                        credit management module.
                    </p>
                </div>
            </div>
        </div>
        
        <?php if ($fixResult): ?>
        <!-- Fix Result -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-<?= $fixResult['success'] ? 'green' : 'red' ?>-50">
                <h2 class="text-lg font-semibold text-<?= $fixResult['success'] ? 'green' : 'red' ?>-700">
                    <?= $fixResult['success'] ? 'Success' : 'Error' ?> - Record #<?= $recordId ?>
                </h2>
            </div>
            <div class="p-6">
                <ul class="list-disc pl-6 space-y-2">
                    <?php foreach ($fixResult['messages'] as $message): ?>
                    <li class="text-sm text-gray-700"><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="mt-4">
                    <a href="fix_credit_records.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-1"></i> Back to all records
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Records Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Cash Records with Credit</h2>
            </div>
            
            <?php if (empty($creditRecords)): ?>
            <div class="p-6 text-center text-gray-500">
                <p>No credit records found.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($creditRecords as $record): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $record['record_id'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('Y-m-d', strtotime($record['record_date'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($record['staff_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                <?= $currency_symbol ?> <?= number_format($record['collected_credit'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $record['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= ucfirst($record['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="?fix=1&record_id=<?= $record['record_id'] ?>" class="text-blue-600 hover:text-blue-900">Fix Record</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
