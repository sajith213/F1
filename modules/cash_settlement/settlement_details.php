<?php
/**
 * Cash Settlement Module - Settlement Details
 * 
 * This page displays the details of a specific cash settlement record
 * and allows for verification or adjustment operations.
 */
ob_start();
// Set page title and include header
$page_title = "Settlement Details";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Cash Settlement</a> / Settlement Details';
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once 'functions.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access the cash settlement module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>Invalid settlement record ID.</p>
          </div>';
    echo '<a href="index.php" class="inline-block mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
          </a>';
    include_once '../../includes/footer.php';
    exit;
}

$record_id = intval($_GET['id']);
$record = getCashRecordById($record_id);

if (!$record) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>Settlement record not found.</p>
          </div>';
    echo '<a href="index.php" class="inline-block mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
          </a>';
    include_once '../../includes/footer.php';
    exit;
}

// Success message (from redirect)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p>Cash settlement record created successfully.</p>
          </div>';
}

// Get currency symbol from settings
$currency_symbol = 'LKR'; // Default
$settings_query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$settings_result = $conn->query($settings_query);
if ($settings_result && $settings_row = $settings_result->fetch_assoc()) {
    $currency_symbol = $settings_row['setting_value'];
}

// Handle verify/approve action
if (isset($_POST['verify']) && $record['status'] == 'pending') {
    $status = 'verified';
    $user_id = $_SESSION['user_id'];
    
    $result = verifyCashRecord($record_id, $user_id, $status);
    
    if ($result) {
        // Refresh the page to see updated data
        header("Location: settlement_details.php?id=$record_id&verified=1");
        exit;
    }
}

// Handle adjustment action
if (isset($_POST['create_adjustment'])) {
    $adjustment_data = [
        'record_id' => $record_id,
        'adjustment_type' => $_POST['adjustment_type'],
        'amount' => floatval($_POST['adjustment_amount']),
        'reason' => $_POST['adjustment_reason'],
        'approved_by' => $_SESSION['user_id'],
        'status' => 'approved', // Auto-approve since it's done by an authorized user
        'notes' => $_POST['adjustment_notes'] ?? ''
    ];
    
    $adjustment_id = createCashAdjustment($adjustment_data);
    
    if ($adjustment_id) {
        // Refresh the page to see updated data
        header("Location: settlement_details.php?id=$record_id&adjusted=1");
        exit;
    }
}

// Display success messages
if (isset($_GET['verified']) && $_GET['verified'] == 1) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p>Settlement record verified successfully.</p>
          </div>';
}

if (isset($_GET['adjusted']) && $_GET['adjusted'] == 1) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p>Adjustment recorded successfully.</p>
          </div>';
}

// Refresh record data in case it was updated
$record = getCashRecordById($record_id);

// Calculate allowable shortage
$allowable_shortage = calculateAllowableShortage($record['expected_amount']);
?>

<!-- Action buttons -->
<div class="mb-6 flex justify-between items-center">
    <a href="daily_settlement.php?date=<?= $record['record_date'] ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <i class="fas fa-chevron-left mr-2"></i> Back to Daily Settlement
    </a>
    
    <?php if ($record['status'] == 'pending' && has_permission('manage_cash')): ?>
    <form method="post" class="inline-block">
        <button type="submit" name="verify" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-check-circle mr-2"></i> Verify Settlement
        </button>
    </form>
    <?php endif; ?>
</div>
<!-- Add this inside the action buttons area -->
<?php if ($record['test_liters'] > 0): ?>
<a href="record_test_liters.php?record_id=<?= $record['record_id'] ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
    <i class="fas fa-tint mr-2"></i> Adjust Tank for Test Liters
</a>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Settlement Details -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-700">Settlement Details</h2>
        </div>
        
        <div class="p-6">
            <!-- Status Badge -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">
                    Record #<?= $record_id ?>
                </h3>
                
                <div>
                    <?php 
                    $status_class = 'bg-yellow-100 text-yellow-800';
                    if ($record['status'] == 'verified') {
                        $status_class = 'bg-green-100 text-green-800';
                    } elseif ($record['status'] == 'disputed') {
                        $status_class = 'bg-red-100 text-red-800';
                    } elseif ($record['status'] == 'settled') {
                        $status_class = 'bg-blue-100 text-blue-800';
                    }
                    ?>
                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?= $status_class ?>">
                        <?= ucfirst($record['status']) ?>
                    </span>
                </div>
            </div>
            
            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Date</h4>
                    <p class="mt-1 text-sm text-gray-900"><?= date('d M Y', strtotime($record['record_date'])) ?></p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Shift</h4>
                    <p class="mt-1 text-sm text-gray-900"><?= ucfirst($record['shift']) ?></p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Staff</h4>
                    <p class="mt-1 text-sm text-gray-900">
                        <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                        <span class="text-gray-500">(<?= htmlspecialchars($record['staff_code']) ?>)</span>
                    </p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Pump</h4>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($record['pump_name']) ?></p>
                </div>
            </div>
            
            <!-- Credit Sales Section -->
            <?php if (!empty($record['credit_entries'])): ?>
            <div class="border-t border-gray-200 pt-4 mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Credit Sales</h4>
                
                <div class="overflow-x-auto bg-white rounded border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($record['credit_entries'] as $entry): ?>
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($entry['customer_name']) ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                                    <?= $currency_symbol ?> <?= number_format($entry['amount'], 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-700">Total Credit</td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                                    <?= $currency_symbol ?> <?= number_format($record['collected_credit'], 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Amount Information -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Expected Amount</h4>
                        <p class="mt-1 text-base font-medium text-gray-900"><?= $currency_symbol ?> <?= number_format($record['expected_amount'], 2) ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Collected Amount</h4>
                        <p class="mt-1 text-base font-medium text-gray-900"><?= $currency_symbol ?> <?= number_format($record['collected_amount'], 2) ?></p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Difference</h4>
                        <?php 
                        $difference_class = 'text-gray-900';
                        if ($record['difference'] > 0) {
                            $difference_class = 'text-green-600';
                        } elseif ($record['difference'] < 0) {
                            $difference_class = 'text-red-600';
                        }
                        ?>
                        <p class="mt-1 text-base font-medium <?= $difference_class ?>">
                            <?= $currency_symbol ?> <?= number_format($record['difference'], 2) ?>
                            <?php if ($record['difference'] > 0): ?>
                                <span class="text-xs font-normal text-green-600">(Excess)</span>
                            <?php elseif ($record['difference'] < 0): ?>
                                <span class="text-xs font-normal text-red-600">(Shortage)</span>
                            <?php else: ?>
                                <span class="text-xs font-normal text-gray-600">(Balanced)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($record['difference'] < 0): ?>
                <div class="mt-3 border-t border-gray-200 pt-3">
                    <p class="text-sm text-gray-600">
                        Allowable shortage (<?= number_format(getAllowableShortagePercentage(), 2) ?>%): 
                        <span class="font-medium"><?= $currency_symbol ?> <?= number_format($allowable_shortage, 2) ?></span>
                    </p>
                    
                    <?php if (abs($record['difference']) <= $allowable_shortage): ?>
                    <p class="text-sm text-green-600 mt-1">
                        <i class="fas fa-check-circle mr-1"></i> 
                        The shortage is within allowable limits.
                    </p>
                    <?php else: ?>
                    <p class="text-sm text-red-600 mt-1">
                        <i class="fas fa-exclamation-circle mr-1"></i> 
                        The shortage exceeds allowable limits by 
                        <?= $currency_symbol ?> <?= number_format(abs($record['difference']) - $allowable_shortage, 2) ?>.
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Verification Information -->
            <?php if ($record['status'] != 'pending'): ?>
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Verification Information</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2">
                    <div>
                        <p class="text-sm text-gray-600">Verified By:</p>
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($record['verifier_name']) ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Verification Date:</p>
                        <p class="text-sm font-medium text-gray-900"><?= date('d M Y, h:i A', strtotime($record['verification_date'])) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Adjustments Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-700">Adjustments</h2>
        </div>
        
        <div class="p-6">
            <?php if (empty($record['adjustments'])): ?>
            <div class="text-center py-4 text-gray-500">
                <i class="fas fa-info-circle mr-2"></i> No adjustments have been made yet.
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($record['adjustments'] as $adjustment): ?>
                <div class="border rounded-lg p-4
                           <?php echo $adjustment['adjustment_type'] == 'allowance' ? 'border-green-200 bg-green-50' : 
                                  ($adjustment['adjustment_type'] == 'deduction' ? 'border-red-200 bg-red-50' : 
                                   'border-gray-200 bg-gray-50'); ?>">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="text-sm font-medium 
                                   <?php echo $adjustment['adjustment_type'] == 'allowance' ? 'text-green-700' : 
                                          ($adjustment['adjustment_type'] == 'deduction' ? 'text-red-700' : 'text-gray-700'); ?>">
                                <?= ucfirst($adjustment['adjustment_type']) ?>
                            </h4>
                            <p class="text-xs text-gray-500"><?= date('d M Y, h:i A', strtotime($adjustment['adjustment_date'])) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-base font-medium 
                                   <?php echo $adjustment['adjustment_type'] == 'allowance' ? 'text-green-700' : 
                                          ($adjustment['adjustment_type'] == 'deduction' ? 'text-red-700' : 'text-gray-700'); ?>">
                                <?= $currency_symbol ?> <?= number_format($adjustment['amount'], 2) ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                Approved by: <?= htmlspecialchars($adjustment['approver_name']) ?>
                            </p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mb-1"><strong>Reason:</strong> <?= htmlspecialchars($adjustment['reason']) ?></p>
                    <?php if (!empty($adjustment['notes'])): ?>
                    <p class="text-sm text-gray-600"><strong>Notes:</strong> <?= htmlspecialchars($adjustment['notes']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- New Adjustment Form (Only for verified records with differences) -->
            <?php if ($record['status'] == 'verified' && $record['difference'] != 0 && has_permission('manage_cash')): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-base font-medium text-gray-900 mb-4">Create New Adjustment</h3>
                
                <form method="post" action="" class="space-y-4">
                    <!-- Adjustment Type -->
                    <div>
                        <label for="adjustment_type" class="block text-sm font-medium text-gray-700 mb-1">Adjustment Type</label>
                        <select id="adjustment_type" name="adjustment_type" required 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <?php if ($record['difference'] < 0): ?>
                            <option value="allowance">Allowance (Staff Not Responsible)</option>
                            <option value="deduction">Deduction (Staff Responsible)</option>
                            <?php else: ?>
                            <option value="allowance">Allowance (Keep Excess)</option>
                            <option value="write-off">Write-off (Return Excess)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <!-- Adjustment Amount -->
                    <div>
                        <label for="adjustment_amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Adjustment Amount (<?= $currency_symbol ?>)
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                            </div>
                            <input type="number" step="0.01" id="adjustment_amount" name="adjustment_amount" 
                                   value="<?= number_format(abs($record['difference']), 2, '.', '') ?>" required
                                   class="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    
                    <!-- Reason -->
                    <div>
                        <label for="adjustment_reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input type="text" id="adjustment_reason" name="adjustment_reason" required
                               placeholder="Reason for adjustment"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    
                    <!-- Notes -->
                    <div>
                        <label for="adjustment_notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes (Optional)</label>
                        <textarea id="adjustment_notes" name="adjustment_notes" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                  placeholder="Any additional notes or details"></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <div>
                        <button type="submit" name="create_adjustment" 
                                class="w-full inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus-circle mr-2"></i> Create Adjustment
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
ob_end_flush();
?>
