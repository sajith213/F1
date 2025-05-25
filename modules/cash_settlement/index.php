<?php
/**
 * Cash Settlement Module - Main Page
 * 
 * This page serves as the main dashboard for the cash settlement module.
 */

// Set page title and include header
$page_title = "Cash Settlement";
$breadcrumbs = '<a href="../../index.php">Home</a> / Cash Settlement';

// Include authentication helper first
require_once '../../includes/auth.php';

// Then include header and other files
require_once '../../includes/header.php';
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

// Get dashboard summary data
$summary = getCashSettlementSummary();

// Get currency symbol
$currency_symbol = 'LKR'; // Default
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currency_symbol = $row['setting_value'];
}

// Filter parameters for settlements list
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$filters = [];

// Apply filters if set
if (isset($_GET['staff_id']) && !empty($_GET['staff_id'])) {
    $filters['staff_id'] = intval($_GET['staff_id']);
}

if (isset($_GET['pump_id']) && !empty($_GET['pump_id'])) {
    $filters['pump_id'] = intval($_GET['pump_id']);
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['shift']) && !empty($_GET['shift'])) {
    $filters['shift'] = $_GET['shift'];
}

// Get cash records with pagination
$records_per_page = 10;
$cash_records_data = getCashRecords($filters, $current_page, $records_per_page);
$cash_records = $cash_records_data['records'];

// Get staff and pumps for filter dropdowns
$all_staff = getAllStaff();
$all_pumps = getAllPumps();
?>

<!-- Dashboard Actions -->
<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Cash Settlement</h1>
    </div>
    
    <div class="flex flex-col sm:flex-row gap-2">
        <a href="daily_settlement.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-plus-circle mr-2"></i> New Settlement
        </a>
        <a href="reports.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2">
            <i class="fas fa-chart-bar mr-2"></i> Reports
        </a>
        <a href="update_credit_records.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
            <i class="fas fa-sync-alt mr-2"></i> Credit Integration
        </a>
        <a href="bulk_test_liters_adjustment.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
            <i class="fas fa-sync mr-2"></i> Bulk Test Liters Adjustment
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Settlements -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Total Settlements</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $summary['status_counts']['total'] ?></h3>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
                <i class="fas fa-file-invoice-dollar text-blue-500 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 flex text-xs text-gray-500">
            <span class="mr-2"><i class="fas fa-circle text-yellow-400 mr-1"></i> Pending: <?= $summary['status_counts']['pending'] ?? 0 ?></span>
            <span class="mr-2"><i class="fas fa-circle text-green-400 mr-1"></i> Verified: <?= $summary['status_counts']['verified'] ?? 0 ?></span>
            <span><i class="fas fa-circle text-red-400 mr-1"></i> Disputed: <?= $summary['status_counts']['disputed'] ?? 0 ?></span>
        </div>
    </div>
    
    <!-- Today's Collections -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Today's Collections</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['today_total'], 2) ?></h3>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
                <i class="fas fa-calendar-day text-green-500 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            <span><?= date('d M Y') ?></span>
        </div>
    </div>
    
    <!-- Weekly Collections -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-purple-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Weekly Collections</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['week_total'], 2) ?></h3>
            </div>
            <div class="p-3 bg-purple-100 rounded-full">
                <i class="fas fa-calendar-week text-purple-500 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            <span><?= date('d M', strtotime('monday this week')) ?> - <?= date('d M Y', strtotime('sunday this week')) ?></span>
        </div>
    </div>
    
    <!-- Monthly Collections -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Monthly Collections</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['month_total'], 2) ?></h3>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
                <i class="fas fa-calendar-alt text-yellow-500 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            <span><?= date('F Y') ?></span>
        </div>
    </div>
</div>

<!-- Settlements List Section -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-lg font-semibold text-gray-700">Settlements List</h2>
    </div>
    
    <!-- Filters -->
    <div class="p-6 border-b border-gray-200 bg-gray-50">
        <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Staff Filter -->
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff</label>
                <select id="staff_id" name="staff_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach($all_staff as $staff): ?>
                    <option value="<?= $staff['staff_id'] ?>" <?= isset($filters['staff_id']) && $filters['staff_id'] == $staff['staff_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($staff['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Pump Filter -->
            <div>
                <label for="pump_id" class="block text-sm font-medium text-gray-700 mb-1">Pump</label>
                <select id="pump_id" name="pump_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Pumps</option>
                    <?php foreach($all_pumps as $pump): ?>
                    <option value="<?= $pump['pump_id'] ?>" <?= isset($filters['pump_id']) && $filters['pump_id'] == $pump['pump_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pump['pump_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= isset($filters['status']) && $filters['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="verified" <?= isset($filters['status']) && $filters['status'] == 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="settled" <?= isset($filters['status']) && $filters['status'] == 'settled' ? 'selected' : '' ?>>Settled</option>
                    <option value="disputed" <?= isset($filters['status']) && $filters['status'] == 'disputed' ? 'selected' : '' ?>>Disputed</option>
                </select>
            </div>
            
            <!-- Date Range -->
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" id="date_from" name="date_from" value="<?= $filters['date_from'] ?? '' ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" id="date_to" name="date_to" value="<?= $filters['date_to'] ?? '' ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <!-- Shift Filter -->
            <div>
                <label for="shift" class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                <select id="shift" name="shift" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Shifts</option>
                    <option value="morning" <?= isset($filters['shift']) && $filters['shift'] == 'morning' ? 'selected' : '' ?>>Morning</option>
                    <option value="afternoon" <?= isset($filters['shift']) && $filters['shift'] == 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
                    <option value="evening" <?= isset($filters['shift']) && $filters['shift'] == 'evening' ? 'selected' : '' ?>>Evening</option>
                    <option value="night" <?= isset($filters['shift']) && $filters['shift'] == 'night' ? 'selected' : '' ?>>Night</option>
                </select>
            </div>
            
            <!-- Filter Buttons -->
            <div class="lg:col-span-3 flex items-end space-x-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>
    
    <!-- Table of Records -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Collected</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Difference</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($cash_records)): ?>
                <tr>
                    <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                        No records found. Please try different filters or <a href="daily_settlement.php" class="text-blue-600 hover:text-blue-800">create a new settlement</a>.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($cash_records as $record): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= $record['record_id'] ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('d M Y', strtotime($record['record_date'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($record['pump_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= ucfirst($record['shift']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            <?= $currency_symbol ?> <?= number_format($record['expected_amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            <?= $currency_symbol ?> <?= number_format($record['collected_amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right
                                  <?= $record['difference'] > 0 ? 'text-green-600' : ($record['difference'] < 0 ? 'text-red-600' : 'text-gray-500') ?>">
                            <?= $currency_symbol ?> <?= number_format($record['difference'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
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
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                <?= ucfirst($record['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="settlement_details.php?id=<?= $record['record_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($cash_records_data['total_pages'] > 1): ?>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing 
                <span class="font-medium"><?= (($current_page - 1) * $records_per_page) + 1 ?></span>
                to 
                <span class="font-medium">
                    <?= min($current_page * $records_per_page, $cash_records_data['total_records']) ?>
                </span>
                of 
                <span class="font-medium"><?= $cash_records_data['total_records'] ?></span>
                results
            </div>
            
            <div class="flex-1 flex justify-end">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <!-- Previous Page Link -->
                    <?php if ($current_page > 1): ?>
                    <a href="<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php else: ?>
                    <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Page Links -->
                    <?php 
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($cash_records_data['total_pages'], $current_page + 2);
                    
                    if ($start_page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?= $i == $current_page ? 'bg-blue-50 text-blue-600 font-bold' : 'bg-white text-gray-700 hover:bg-gray-50' ?> text-sm font-medium">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $cash_records_data['total_pages']): ?>
                    <?php if ($end_page < $cash_records_data['total_pages'] - 1): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                    <?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $cash_records_data['total_pages']])) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <?= $cash_records_data['total_pages'] ?>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Next Page Link -->
                    <?php if ($current_page < $cash_records_data['total_pages']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php else: ?>
                    <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                    </span>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>
