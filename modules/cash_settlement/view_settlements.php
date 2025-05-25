<?php
/**
 * Cash Settlement Module - View Settlements
 * 
 * This page displays a list of all cash settlement records with filtering options
 */

// Set page title and include header
$page_title = "View Cash Settlements";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Cash Settlement</a> / View Settlements';
require_once '../../includes/header.php';
require_once 'functions.php';

// Get filters from request
$filters = [];
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

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
$result = getCashRecords($filters, $page);
$records = $result['records'];
$total_pages = $result['total_pages'];
$current_page = $result['current_page'];
$total_records = $result['total_records'];

// Get all staff and pumps for filter dropdowns
$all_staff = getAllStaff();
$all_pumps = getAllPumps();
?>

<!-- Filter Section -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <h2 class="text-lg font-semibold text-gray-700 mb-4">Search Filters</h2>
    
    <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Staff Filter -->
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                <select id="staff_id" name="staff_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): ?>
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
                    <?php foreach ($all_pumps as $pump): ?>
                    <option value="<?= $pump['pump_id'] ?>" <?= isset($filters['pump_id']) && $filters['pump_id'] == $pump['pump_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pump['pump_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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
            
            <!-- Date From Filter -->
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" id="date_from" name="date_from" value="<?= isset($filters['date_from']) ? $filters['date_from'] : '' ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <!-- Date To Filter -->
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" id="date_to" name="date_to" value="<?= isset($filters['date_to']) ? $filters['date_to'] : '' ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
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
        </div>
        
        <div class="flex justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-search mr-2"></i> Search
            </button>
            
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-times mr-2"></i> Clear Filters
            </a>
        </div>
    </form>
</div>

<!-- Results Section -->
<div class="bg-white rounded-lg shadow-md p-4">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-700">Cash Settlement Records</h2>
        <a href="daily_settlement.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            <i class="fas fa-plus mr-2"></i> New Settlement
        </a>
    </div>
    
    <div class="mb-4 text-sm text-gray-500">
        Showing <?= count($records) ?> of <?= $total_records ?> records
    </div>
    
    <?php if (empty($records)): ?>
    <div class="text-center py-8 text-gray-500">
        <i class="fas fa-info-circle text-2xl mb-2"></i>
        <p>No settlement records found matching your criteria.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        ID
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Staff
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Pump
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Expected
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Collected
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Difference
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($records as $record): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= $record['record_id'] ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= date('d M Y', strtotime($record['record_date'])) ?></div>
                        <div class="text-xs text-gray-500"><?= ucfirst($record['shift']) ?> Shift</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($record['pump_name']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= CURRENCY_SYMBOL . number_format($record['expected_amount'], 2) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= CURRENCY_SYMBOL . number_format($record['collected_amount'], 2) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($record['difference'] > 0): ?>
                        <div class="text-sm text-green-600">+<?= CURRENCY_SYMBOL . number_format($record['difference'], 2) ?></div>
                        <?php elseif ($record['difference'] < 0): ?>
                        <div class="text-sm text-red-600"><?= CURRENCY_SYMBOL . number_format($record['difference'], 2) ?></div>
                        <?php else: ?>
                        <div class="text-sm text-gray-600"><?= CURRENCY_SYMBOL . '0.00' ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($record['status'] == 'pending'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            Pending
                        </span>
                        <?php elseif ($record['status'] == 'verified'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            Verified
                        </span>
                        <?php elseif ($record['status'] == 'settled'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Settled
                        </span>
                        <?php elseif ($record['status'] == 'disputed'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                            Disputed
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="settlement_details.php?id=<?= $record['record_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($record['status'] == 'pending' && $_SESSION['role'] == 'manager'): ?>
                        <a href="settlement_details.php?id=<?= $record['record_id'] ?>&action=verify" class="text-green-600 hover:text-green-900 mr-3">
                            <i class="fas fa-check"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <!-- Previous Page -->
            <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>" 
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
            
            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            
            if ($start_page > 1) {
                echo '<a href="?page=1' . (http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '') . '" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    1
                </a>';
                
                if ($start_page > 2) {
                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $current_page) {
                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                        ' . $i . '
                    </span>';
                } else {
                    echo '<a href="?page=' . $i . (http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '') . '" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        ' . $i . '
                    </a>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>';
                }
                
                echo '<a href="?page=' . $total_pages . (http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '') . '" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    ' . $total_pages . '
                </a>';
            }
            ?>
            
            <!-- Next Page -->
            <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>" 
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
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>