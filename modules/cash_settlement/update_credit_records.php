<?php
/**
 * Cash Settlement Module - Update Credit Transactions in Credit Management
 * 
 * This script ensures that credit transactions in cash settlement are properly registered in credit management
 */

// Set page title and include header
$page_title = "Credit Integration";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Cash Settlement</a> / <span class="text-gray-700">Credit Integration</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once 'hooks.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Function to get all credit cash records
function getAllCreditCashRecords($conn) {
    $query = "
        SELECT dcr.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name
        FROM daily_cash_records dcr
        JOIN staff s ON dcr.staff_id = s.staff_id 
        WHERE dcr.collected_credit > 0
        ORDER BY dcr.record_date DESC
        LIMIT 100
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

// Process update request
$updateResult = null;
if (isset($_POST['update_record']) && isset($_POST['record_id']) && is_numeric($_POST['record_id'])) {
    $recordId = intval($_POST['record_id']);
    
    // First, check if this record exists and has credit amount
    $stmt = $conn->prepare("
        SELECT dcr.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name, s.staff_id
        FROM daily_cash_records dcr
        JOIN staff s ON dcr.staff_id = s.staff_id 
        WHERE dcr.record_id = ? AND dcr.collected_credit > 0
    ");
    
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $updateResult = [
            'success' => false,
            'message' => "Cash record #{$recordId} not found or has no credit amount."
        ];
    } else {
        $cashRecord = $result->fetch_assoc();
        $stmt->close();
        
        // Get credit entries for this record
        $creditEntries = getCreditEntriesForRecord($conn, $recordId);
        
        if (empty($creditEntries)) {
            $updateResult = [
                'success' => false,
                'message' => "No credit entries found for this record."
            ];
        } else {
            $successes = 0;
            $errors = 0;
            $messages = [];
            
            // Process each credit entry
            foreach ($creditEntries as $entry) {
                try {
                    // Create a credit sale in the credit management module
                    $sale_id = createCreditSale(
                        $entry['customer_id'],
                        $entry['amount'],
                        $recordId,
                        $cashRecord['record_date'],
                        $cashRecord['staff_id']
                    );
                    
                    if ($sale_id) {
                        $successes++;
                        $messages[] = "Successfully registered credit sale for customer ID: {$entry['customer_id']}, amount: {$entry['amount']}";
                    } else {
                        $errors++;
                        $messages[] = "Failed to register credit sale for customer ID: {$entry['customer_id']}, amount: {$entry['amount']}";
                    }
                } catch (Exception $e) {
                    $errors++;
                    $messages[] = "Error processing credit entry for customer ID: {$entry['customer_id']}: " . $e->getMessage();
                    
                    // Check for specific errors
                    if (strpos($e->getMessage(), "reference") !== false) {
                        $messages[] = "Database structure issue: There may be a column mismatch in the sales table. Try updating the database structure with the proper columns.";
                    }
                    if (strpos($e->getMessage(), "credit_sales") !== false) {
                        $messages[] = "Please ensure the credit_sales table is properly configured.";
                    }
                }
            }
            
            if ($successes > 0) {
                $updateResult = [
                    'success' => true,
                    'message' => "Successfully registered {$successes} credit sales in credit management module.",
                    'details' => $messages
                ];
                
                if ($errors > 0) {
                    $updateResult['message'] .= " Encountered {$errors} errors.";
                }
            } else {
                $updateResult = [
                    'success' => false,
                    'message' => "Failed to register any credit sales in credit management module.",
                    'details' => $messages
                ];
            }
        }
    }
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
?>

<div class="container mx-auto pb-6">
    <!-- Action buttons and title row -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Cash-Credit Integration</h2>
        
        <div>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to Module
            </a>
        </div>
    </div>
    
    <!-- Explanation Panel -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    This utility ensures that credit sales from the cash settlement module are properly registered in the credit management module.
                    If you notice credit sales from daily cash settlements are not appearing in the credit management module, use this tool to fix them.
                </p>
            </div>
        </div>
    </div>
    
    <?php if ($updateResult): ?>
    <!-- Result Alert -->
    <div class="bg-<?= $updateResult['success'] ? 'green' : 'red' ?>-100 border-l-4 border-<?= $updateResult['success'] ? 'green' : 'red' ?>-500 text-<?= $updateResult['success'] ? 'green' : 'red' ?>-700 p-4 mb-6">
        <p class="font-bold"><?= $updateResult['success'] ? 'Success' : 'Error' ?></p>
        <p><?= htmlspecialchars($updateResult['message']) ?></p>
        
        <?php if (isset($updateResult['details']) && !empty($updateResult['details'])): ?>
        <ul class="mt-2 list-disc ml-5">
            <?php foreach ($updateResult['details'] as $detail): ?>
            <li class="text-sm"><?= htmlspecialchars($detail) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Records Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Cash Records with Credit</h3>
            <p class="text-sm text-gray-600 mt-1">
                These records have credit amounts that should be registered in the credit management system.
            </p>
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
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                            <form method="post" action="" class="inline-block">
                                <input type="hidden" name="record_id" value="<?= $record['record_id'] ?>">
                                <button type="submit" name="update_record" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-sync-alt mr-1"></i> Register in Credit Management
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
