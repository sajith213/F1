<?php
/**
 * Credit Management - Update Customer Balances
 * 
 * This script updates the customer balances based on credit transactions
 */

// Set page title and include header
$page_title = "Update Credit Balances";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="index.php">Credit Management</a> / <span class="text-gray-700">Update Balances</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Function to update balances
function updateCustomerBalances() {
    global $conn;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get all credit customers
        $customers_query = "SELECT customer_id, customer_name FROM credit_customers";
        $customers_result = $conn->query($customers_query);
        
        $updated_count = 0;
        $error_count = 0;
        $customers_processed = [];
        
        if ($customers_result) {
            while ($customer = $customers_result->fetch_assoc()) {
                $customer_id = $customer['customer_id'];
                $customer_name = $customer['customer_name'];
                
                // Calculate total from credit_sales
                $sales_query = "
                    SELECT COALESCE(SUM(remaining_amount), 0) as total_sales
                    FROM credit_sales
                    WHERE customer_id = ? AND status != 'paid'
                ";
                
                $stmt = $conn->prepare($sales_query);
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $sales_result = $stmt->get_result();
                $sales_total = $sales_result->fetch_assoc()['total_sales'];
                $stmt->close();
                
                // Update the customer balance
                $update_query = "UPDATE credit_customers SET current_balance = ? WHERE customer_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("di", $sales_total, $customer_id);
                
                if ($stmt->execute()) {
                    $updated_count++;
                    $customers_processed[] = [
                        'customer_id' => $customer_id,
                        'customer_name' => $customer_name,
                        'new_balance' => $sales_total
                    ];
                } else {
                    $error_count++;
                }
                $stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'updated_count' => $updated_count,
            'error_count' => $error_count,
            'customers_processed' => $customers_processed
        ];
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Process update if requested
$update_result = null;
if (isset($_POST['update_balances'])) {
    $update_result = updateCustomerBalances();
}

// Get currency symbol from settings
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
        <h2 class="text-2xl font-bold text-gray-800">Update Credit Balances</h2>
        
        <div>
            <a href="credit_customers.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-users mr-2"></i> Credit Customers
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
                    This utility updates customer balances based on credit sales records. Use this if you notice discrepancies between 
                    customer balances and actual sales data. This will recalculate all customer balances based on unpaid credit sales.
                </p>
            </div>
        </div>
    </div>
    
    <!-- Update Form -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Update Customer Balances</h3>
        </div>
        
        <div class="p-6">
            <form method="post" action="">
                <div class="mb-4">
                    <p class="text-gray-700 mb-2">
                        This will update all customer balances to reflect their current outstanding credit sales.
                    </p>
                    <p class="text-sm text-gray-500 mb-4">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>
                        This operation may take some time if you have many customers or sales records.
                    </p>
                </div>
                
                <div class="flex justify-center">
                    <button type="submit" name="update_balances" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded">
                        <i class="fas fa-sync-alt mr-2"></i> Update All Balances
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($update_result): ?>
    <!-- Results Panel -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Update Results</h3>
        </div>
        
        <div class="p-6">
            <?php if ($update_result['success']): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    <p class="font-bold">Success</p>
                    <p>Updated <?= $update_result['updated_count'] ?> customer balances successfully.</p>
                    <?php if ($update_result['error_count'] > 0): ?>
                        <p class="mt-1 text-yellow-600">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Failed to update <?= $update_result['error_count'] ?> customer balances.
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4">
                    <h4 class="text-base font-medium text-gray-700 mb-2">Updated Customers</h4>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">New Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($update_result['customers_processed'] as $customer): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $customer['customer_id'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($customer['customer_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        <?= $currency_symbol ?> <?= number_format($customer['new_balance'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                    <p class="font-bold">Error</p>
                    <p><?= htmlspecialchars($update_result['message']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?>
