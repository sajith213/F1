<?php
/**
 * Bulk Test Liters Adjustment Script
 * 
 * This script finds all cash settlement records with test liters
 * and adds those liters back to the respective fuel tanks.
 */

// Show all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../modules/tank_management/functions.php';

// Set page title
$page_title = "Bulk Test Liters Adjustment";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Cash Settlement</a> / Bulk Test Liters Adjustment';

// Check if user has sufficient permissions
if (!has_permission('manage_cash')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access this feature.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$preview_mode = !isset($_POST['execute']) || ($_POST['execute'] != 'true');
$success_count = 0;
$error_count = 0;
$results = [];
$records_to_adjust = [];
$already_processed = [];

// Track which records have already been processed during this session
$session_processed = [];

// Process the adjustments
if (isset($_POST['execute']) && $_POST['execute'] == 'true') {
    global $conn;
    
    // Get all cash records with test liters in the specified date range
    $query = "SELECT dcr.record_id, dcr.pump_id, dcr.record_date, dcr.shift, 
                    crd.test_liters, crd.fuel_price_at_time, 
                    s.first_name, s.last_name, p.pump_name
              FROM daily_cash_records dcr
              LEFT JOIN cash_record_details crd ON dcr.record_id = crd.record_id
              LEFT JOIN staff s ON dcr.staff_id = s.staff_id
              LEFT JOIN pumps p ON dcr.pump_id = p.pump_id
              WHERE dcr.record_date BETWEEN ? AND ?
              AND crd.test_liters > 0
              ORDER BY dcr.record_date DESC, dcr.record_id DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p class="font-bold">Database Error</p>
                <p>Failed to prepare query: ' . $conn->error . '</p>
              </div>';
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $records = $stmt->get_result();
        
        while ($record = $records->fetch_assoc()) {
            $record_id = $record['record_id'];
            $pump_id = $record['pump_id'];
            $test_liters = $record['test_liters'];
            
            // Skip if we've already processed this record in this session
            if (in_array($record_id, $session_processed)) {
                $results[] = [
                    'record_id' => $record_id,
                    'pump_name' => $record['pump_name'],
                    'staff_name' => $record['first_name'] . ' ' . $record['last_name'],
                    'date' => $record['record_date'],
                    'shift' => $record['shift'],
                    'test_liters' => $test_liters,
                    'status' => 'skipped',
                    'message' => 'Already processed in this session'
                ];
                continue;
            }
            
            // Add to session processed list
            $session_processed[] = $record_id;
            
            // Attempt to adjust the tank
            $result = recordTestLitersAdjustment($pump_id, $test_liters, $record_id);
            
            if ($result) {
                $success_count++;
                $results[] = [
                    'record_id' => $record_id,
                    'pump_name' => $record['pump_name'],
                    'staff_name' => $record['first_name'] . ' ' . $record['last_name'],
                    'date' => $record['record_date'],
                    'shift' => $record['shift'],
                    'test_liters' => $test_liters,
                    'status' => 'success',
                    'message' => 'Adjustment recorded'
                ];
            } else {
                $error_count++;
                $results[] = [
                    'record_id' => $record_id,
                    'pump_name' => $record['pump_name'],
                    'staff_name' => $record['first_name'] . ' ' . $record['last_name'],
                    'date' => $record['record_date'],
                    'shift' => $record['shift'],
                    'test_liters' => $test_liters,
                    'status' => 'error',
                    'message' => 'Failed to adjust'
                ];
            }
        }
        $stmt->close();
    }
}

// Get records with test liters for preview
if ($preview_mode) {
    global $conn;
    
    $query = "SELECT dcr.record_id, dcr.pump_id, dcr.record_date, dcr.shift, 
                    crd.test_liters, crd.fuel_price_at_time, 
                    s.first_name, s.last_name, p.pump_name
              FROM daily_cash_records dcr
              LEFT JOIN cash_record_details crd ON dcr.record_id = crd.record_id
              LEFT JOIN staff s ON dcr.staff_id = s.staff_id
              LEFT JOIN pumps p ON dcr.pump_id = p.pump_id
              WHERE dcr.record_date BETWEEN ? AND ?
              AND crd.test_liters > 0
              ORDER BY dcr.record_date DESC, dcr.record_id DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p class="font-bold">Database Error</p>
                <p>Failed to prepare query: ' . $conn->error . '</p>
              </div>';
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Since we don't have tank_operations table, we'll mark all records as not adjusted
            $row['already_adjusted'] = false;
            $records_to_adjust[] = $row;
        }
        $stmt->close();
    }
}

// Create a simple warning about duplicate adjustments
$duplicate_warning = '';
if ($preview_mode && !empty($records_to_adjust)) {
    $duplicate_warning = '<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Warning:</strong> This tool does not track which records have already been adjusted. 
                    If you run it multiple times, test liters may be added to tanks multiple times. 
                    Only process records you haven\'t adjusted before.
                </p>
            </div>
        </div>
    </div>';
}
?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Bulk Test Liters Adjustment</h1>
    
    <div class="mb-6">
        <p class="text-gray-600 mb-4">
            This utility will find all cash settlement records with test liters and add those liters back to the respective fuel tanks.
            Test liters are used during verification but should be returned to the tank inventory.
        </p>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        Warning: This operation will modify tank volumes. Make sure you understand the consequences before proceeding.
                    </p>
                </div>
            </div>
        </div>
        
        <?php echo $duplicate_warning; ?>
    </div>
    
    <!-- Date Range Filter -->
    <form method="GET" action="bulk_test_liters_adjustment.php" class="mb-6 p-4 bg-gray-50 rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            <div>
                <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter Records
                </button>
            </div>
        </div>
    </form>
    
    <?php if (!empty($results)): ?>
    <!-- Execution Results -->
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Execution Results</h2>
        
        <div class="bg-gray-100 p-4 mb-4 rounded-lg flex justify-between">
            <div class="text-center">
                <span class="block text-2xl font-bold"><?= count($results) ?></span>
                <span class="text-sm text-gray-600">Total Processed</span>
            </div>
            <div class="text-center">
                <span class="block text-2xl font-bold text-green-600"><?= $success_count ?></span>
                <span class="text-sm text-gray-600">Successful</span>
            </div>
            <div class="text-center">
                <span class="block text-2xl font-bold text-red-600"><?= $error_count ?></span>
                <span class="text-sm text-gray-600">Failed</span>
            </div>
            <div class="text-center">
                <span class="block text-2xl font-bold text-gray-500"><?= count($results) - $success_count - $error_count ?></span>
                <span class="text-sm text-gray-600">Skipped</span>
            </div>
        </div>
        
        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date / Shift</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Test Liters</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $result): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <a href="settlement_details.php?id=<?= $result['record_id'] ?>" class="text-blue-600 hover:underline">
                                #<?= $result['record_id'] ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= date('d M Y', strtotime($result['date'])) ?> / <?= ucfirst($result['shift']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($result['pump_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($result['staff_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                            <?= number_format($result['test_liters'], 2) ?> L
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <?php if ($result['status'] == 'success'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i> <?= $result['message'] ?>
                            </span>
                            <?php elseif ($result['status'] == 'error'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                <i class="fas fa-times-circle mr-1"></i> <?= $result['message'] ?>
                            </span>
                            <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                <i class="fas fa-info-circle mr-1"></i> <?= $result['message'] ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($preview_mode): ?>
    <!-- Preview Mode -->
    <div>
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Records Found</h2>
        
        <?php if (empty($records_to_adjust)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        No records with test liters found in the selected date range.
                    </p>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <p class="text-gray-600 mb-4">
            Found <?= count($records_to_adjust) ?> records with test liters. Review the records below and click 'Process Adjustments' to apply them.
        </p>
        
        <div class="overflow-x-auto bg-white rounded-lg shadow mb-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date / Shift</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Test Liters</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $total_liters = 0;
                    $pending_count = 0;
                    foreach ($records_to_adjust as $record): 
                        $total_liters += $record['test_liters'];
                        if (!$record['already_adjusted']) {
                            $pending_count++;
                        }
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <a href="settlement_details.php?id=<?= $record['record_id'] ?>" class="text-blue-600 hover:underline">
                                #<?= $record['record_id'] ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= date('d M Y', strtotime($record['record_date'])) ?> / <?= ucfirst($record['shift']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($record['pump_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                            <?= number_format($record['test_liters'], 2) ?> L
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                Pending
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (count($records_to_adjust) > 0): ?>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-right text-sm font-medium text-gray-700">
                            Total:
                        </td>
                        <td class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                            <?= number_format($total_liters, 2) ?> L
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        
        <?php if ($pending_count > 0): ?>
        <form method="POST" action="bulk_test_liters_adjustment.php" class="text-right">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <input type="hidden" name="execute" value="true">
            
            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-cogs mr-2"></i> Process <?= $pending_count ?> Pending Adjustment<?= $pending_count != 1 ? 's' : '' ?>
            </button>
        </form>
        <?php else: ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        All records in this date range have already been adjusted. No action needed.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Back to List -->
    <div class="mt-6">
        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-arrow-left mr-2"></i> Back to Cash Settlement
        </a>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>