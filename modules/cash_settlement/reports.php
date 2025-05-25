<?php
/**
 * Cash Settlement Module - Reports
 * 
 * This page provides various reports for cash settlements
 */

// Set page title and include header
$page_title = "Cash Settlement Reports";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Cash Settlement</a> / Reports';
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once 'functions.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access the cash settlement reports.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get currency symbol from settings
$currency_symbol = 'LKR'; // Default
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currency_symbol = $row['setting_value'];
}

// Get user inputs
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$pump_id = isset($_GET['pump_id']) ? intval($_GET['pump_id']) : 0;
$shift = isset($_GET['shift']) ? $_GET['shift'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Get all staff and pumps for filter dropdowns
$all_staff = getAllStaff();
$all_pumps = getAllPumps();

// Prepare filters for the report
$filters = [
    'date_from' => $date_from,
    'date_to' => $date_to
];

if ($staff_id > 0) {
    $filters['staff_id'] = $staff_id;
}

if ($pump_id > 0) {
    $filters['pump_id'] = $pump_id;
}

if (!empty($shift)) {
    $filters['shift'] = $shift;
}

if (!empty($status)) {
    $filters['status'] = $status;
}

// Get all records based on filters
$all_records = getCashRecords($filters, 1, 1000)['records']; // Get up to 1000 records for the report

// Prepare report data based on report type
$report_data = [];
$chart_data = [];

if ($report_type === 'daily') {
    // Group data by date
    $by_date = [];
    foreach ($all_records as $record) {
        $date = $record['record_date'];
        if (!isset($by_date[$date])) {
            $by_date[$date] = [
                'date' => $date,
                'expected' => 0,
                'collected' => 0,
                'difference' => 0,
                'count' => 0
            ];
        }
        
        $by_date[$date]['expected'] += $record['expected_amount'];
        $by_date[$date]['collected'] += $record['collected_amount'];
        $by_date[$date]['difference'] += $record['difference'];
        $by_date[$date]['count']++;
    }
    
    // Sort by date
    ksort($by_date);
    $report_data = array_values($by_date);
    
    // Prepare chart data
    foreach ($report_data as $row) {
        $chart_data[] = [
            'label' => date('d M', strtotime($row['date'])),
            'expected' => $row['expected'],
            'collected' => $row['collected'],
            'difference' => $row['difference']
        ];
    }
} elseif ($report_type === 'weekly') {
    // Group data by week
    $by_week = [];
    foreach ($all_records as $record) {
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($record['record_date'])));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($record['record_date'])));
        $week_key = $week_start . ' to ' . $week_end;
        
        if (!isset($by_week[$week_key])) {
            $by_week[$week_key] = [
                'week' => $week_key,
                'start_date' => $week_start,
                'end_date' => $week_end,
                'expected' => 0,
                'collected' => 0,
                'difference' => 0,
                'count' => 0
            ];
        }
        
        $by_week[$week_key]['expected'] += $record['expected_amount'];
        $by_week[$week_key]['collected'] += $record['collected_amount'];
        $by_week[$week_key]['difference'] += $record['difference'];
        $by_week[$week_key]['count']++;
    }
    
    // Sort by week start date
    uasort($by_week, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });
    
    $report_data = array_values($by_week);
    
    // Prepare chart data
    foreach ($report_data as $row) {
        $chart_data[] = [
            'label' => date('d M', strtotime($row['start_date'])) . ' - ' . date('d M', strtotime($row['end_date'])),
            'expected' => $row['expected'],
            'collected' => $row['collected'],
            'difference' => $row['difference']
        ];
    }
} elseif ($report_type === 'monthly') {
    // Group data by month
    $by_month = [];
    foreach ($all_records as $record) {
        $month = date('Y-m', strtotime($record['record_date']));
        
        if (!isset($by_month[$month])) {
            $by_month[$month] = [
                'month' => $month,
                'month_name' => date('F Y', strtotime($record['record_date'])),
                'expected' => 0,
                'collected' => 0,
                'difference' => 0,
                'count' => 0
            ];
        }
        
        $by_month[$month]['expected'] += $record['expected_amount'];
        $by_month[$month]['collected'] += $record['collected_amount'];
        $by_month[$month]['difference'] += $record['difference'];
        $by_month[$month]['count']++;
    }
    
    // Sort by month
    ksort($by_month);
    $report_data = array_values($by_month);
    
    // Prepare chart data
    foreach ($report_data as $row) {
        $chart_data[] = [
            'label' => $row['month_name'],
            'expected' => $row['expected'],
            'collected' => $row['collected'],
            'difference' => $row['difference']
        ];
    }
} elseif ($report_type === 'staff') {
    // Group data by staff
    $by_staff = [];
    foreach ($all_records as $record) {
        $staff_id = $record['staff_id'];
        $staff_name = $record['first_name'] . ' ' . $record['last_name'];
        
        if (!isset($by_staff[$staff_id])) {
            $by_staff[$staff_id] = [
                'staff_id' => $staff_id,
                'staff_name' => $staff_name,
                'expected' => 0,
                'collected' => 0,
                'difference' => 0,
                'count' => 0
            ];
        }
        
        $by_staff[$staff_id]['expected'] += $record['expected_amount'];
        $by_staff[$staff_id]['collected'] += $record['collected_amount'];
        $by_staff[$staff_id]['difference'] += $record['difference'];
        $by_staff[$staff_id]['count']++;
    }
    
    // Sort by staff name
    uasort($by_staff, function($a, $b) {
        return strcmp($a['staff_name'], $b['staff_name']);
    });
    
    $report_data = array_values($by_staff);
    
    // Prepare chart data
    foreach ($report_data as $row) {
        $chart_data[] = [
            'label' => $row['staff_name'],
            'expected' => $row['expected'],
            'collected' => $row['collected'],
            'difference' => $row['difference']
        ];
    }
} elseif ($report_type === 'pump') {
    // Group data by pump
    $by_pump = [];
    foreach ($all_records as $record) {
        $pump_id = $record['pump_id'];
        $pump_name = $record['pump_name'];
        
        if (!isset($by_pump[$pump_id])) {
            $by_pump[$pump_id] = [
                'pump_id' => $pump_id,
                'pump_name' => $pump_name,
                'expected' => 0,
                'collected' => 0,
                'difference' => 0,
                'count' => 0
            ];
        }
        
        $by_pump[$pump_id]['expected'] += $record['expected_amount'];
        $by_pump[$pump_id]['collected'] += $record['collected_amount'];
        $by_pump[$pump_id]['difference'] += $record['difference'];
        $by_pump[$pump_id]['count']++;
    }
    
    // Sort by pump name
    uasort($by_pump, function($a, $b) {
        return strcmp($a['pump_name'], $b['pump_name']);
    });
    
    $report_data = array_values($by_pump);
    
    // Prepare chart data
    foreach ($report_data as $row) {
        $chart_data[] = [
            'label' => $row['pump_name'],
            'expected' => $row['expected'],
            'collected' => $row['collected'],
            'difference' => $row['difference']
        ];
    }
} elseif ($report_type === 'shift') {
    // Group data by shift
    $by_shift = [];
    $shift_order = ['morning' => 1, 'afternoon' => 2, 'evening' => 3, 'night' => 4];
    
    foreach ($all_records as $record) {
        $shift = $record['shift'];
        
        if (!isset($by_shift[$shift])) {
            $by_shift[$shift] = [
                'shift' => $shift,
                'shift_name' => ucfirst($shift),
                'expected' => 0,
                'collected' => 0,
                'difference' => 0,
                'count' => 0
            ];
        }
        
        $by_shift[$shift]['expected'] += $record['expected_amount'];
        $by_shift[$shift]['collected'] += $record['collected_amount'];
        $by_shift[$shift]['difference'] += $record['difference'];
        $by_shift[$shift]['count']++;
    }
    
    // Sort by shift order
    uasort($by_shift, function($a, $b) use ($shift_order) {
        return $shift_order[$a['shift']] - $shift_order[$b['shift']];
    });
    
    $report_data = array_values($by_shift);
    
    // Prepare chart data
    foreach ($report_data as $row) {
        $chart_data[] = [
            'label' => $row['shift_name'],
            'expected' => $row['expected'],
            'collected' => $row['collected'],
            'difference' => $row['difference']
        ];
    }
}

// Calculate totals
$total_expected = 0;
$total_collected = 0;
$total_difference = 0;
$total_records = 0;

foreach ($report_data as $row) {
    $total_expected += $row['expected'];
    $total_collected += $row['collected'];
    $total_difference += $row['difference'];
    $total_records += $row['count'];
}

// Convert chart data to JSON for JavaScript
$chart_data_json = json_encode($chart_data);
?>

<!-- Report Header -->
<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Cash Settlement Reports</h1>
    </div>
    
    <div class="flex flex-col sm:flex-row gap-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-arrow-left mr-2"></i> Back to Cash Settlement
        </a>
        
        <button onclick="printReport()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-print mr-2"></i> Print Report
        </button>
        
        <button onclick="exportCSV()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-file-csv mr-2"></i> Export CSV
        </button>
    </div>
</div>

<!-- Report Filters -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-lg font-semibold text-gray-700">Report Filters</h2>
    </div>
    
    <div class="p-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Report Type -->
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>Daily Report</option>
                    <option value="weekly" <?= $report_type === 'weekly' ? 'selected' : '' ?>>Weekly Report</option>
                    <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly Report</option>
                    <option value="staff" <?= $report_type === 'staff' ? 'selected' : '' ?>>Staff Report</option>
                    <option value="pump" <?= $report_type === 'pump' ? 'selected' : '' ?>>Pump Report</option>
                    <option value="shift" <?= $report_type === 'shift' ? 'selected' : '' ?>>Shift Report</option>
                </select>
            </div>
            
            <!-- Date Range -->
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" id="date_from" name="date_from" value="<?= $date_from ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" id="date_to" name="date_to" value="<?= $date_to ?>" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </div>
            
            <!-- Staff Filter -->
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff</label>
                <select id="staff_id" name="staff_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach($all_staff as $staff): ?>
                    <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
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
                    <option value="<?= $pump['pump_id'] ?>" <?= $pump_id == $pump['pump_id'] ? 'selected' : '' ?>>
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
                    <option value="morning" <?= $shift === 'morning' ? 'selected' : '' ?>>Morning</option>
                    <option value="afternoon" <?= $shift === 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
                    <option value="evening" <?= $shift === 'evening' ? 'selected' : '' ?>>Evening</option>
                    <option value="night" <?= $shift === 'night' ? 'selected' : '' ?>>Night</option>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div class="lg:col-span-3">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="mt-1 block w-full lg:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="settled" <?= $status === 'settled' ? 'selected' : '' ?>>Settled</option>
                    <option value="disputed" <?= $status === 'disputed' ? 'selected' : '' ?>>Disputed</option>
                </select>
            </div>
            
            <!-- Submit Button -->
            <div class="lg:col-span-3">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Generate Report
                </button>
                <a href="reports.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ml-2">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Report Content -->
<div id="report-container">
    <!-- Report Title -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="text-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">
                <?php
                switch ($report_type) {
                    case 'daily':
                        echo 'Daily Cash Settlement Report';
                        break;
                    case 'weekly':
                        echo 'Weekly Cash Settlement Report';
                        break;
                    case 'monthly':
                        echo 'Monthly Cash Settlement Report';
                        break;
                    case 'staff':
                        echo 'Staff Cash Settlement Report';
                        break;
                    case 'pump':
                        echo 'Pump Cash Settlement Report';
                        break;
                    case 'shift':
                        echo 'Shift Cash Settlement Report';
                        break;
                }
                ?>
            </h2>
            <p class="text-gray-600">Period: <?= date('d M Y', strtotime($date_from)) ?> to <?= date('d M Y', strtotime($date_to)) ?></p>
            
            <?php if ($staff_id > 0): ?>
                <?php 
                $staff_name = '';
                foreach ($all_staff as $staff) {
                    if ($staff['staff_id'] == $staff_id) {
                        $staff_name = $staff['full_name'];
                        break;
                    }
                }
                ?>
                <p class="text-gray-600">Staff: <?= htmlspecialchars($staff_name) ?></p>
            <?php endif; ?>
            
            <?php if ($pump_id > 0): ?>
                <?php 
                $pump_name = '';
                foreach ($all_pumps as $pump) {
                    if ($pump['pump_id'] == $pump_id) {
                        $pump_name = $pump['pump_name'];
                        break;
                    }
                }
                ?>
                <p class="text-gray-600">Pump: <?= htmlspecialchars($pump_name) ?></p>
            <?php endif; ?>
            
            <?php if (!empty($shift)): ?>
                <p class="text-gray-600">Shift: <?= ucfirst($shift) ?></p>
            <?php endif; ?>
            
            <?php if (!empty($status)): ?>
                <p class="text-gray-600">Status: <?= ucfirst($status) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Records -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-sm font-medium text-gray-500 mb-1">Total Records</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $total_records ?></h3>
            </div>
            
            <!-- Expected Amount -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-sm font-medium text-gray-500 mb-1">Expected Amount</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($total_expected, 2) ?></h3>
            </div>
            
            <!-- Collected Amount -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-sm font-medium text-gray-500 mb-1">Collected Amount</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($total_collected, 2) ?></h3>
            </div>
            
            <!-- Difference -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-sm font-medium text-gray-500 mb-1">Difference</p>
                <h3 class="text-2xl font-bold <?= $total_difference > 0 ? 'text-green-600' : ($total_difference < 0 ? 'text-red-600' : 'text-gray-800') ?>">
                    <?= $currency_symbol ?> <?= number_format($total_difference, 2) ?>
                </h3>
                <?php if ($total_difference !== 0): ?>
                <p class="text-xs <?= $total_difference > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $total_difference > 0 ? 'Excess' : 'Shortage' ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chart -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Report Visualization</h3>
            <div style="height: 300px;">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
        
        <!-- Report Table -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Report Details</h3>
            
            <?php if (empty($report_data)): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <p class="text-sm text-yellow-700">
                    No data found for the selected filters. Please try different filter criteria.
                </p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if ($report_type === 'daily'): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <?php elseif ($report_type === 'weekly'): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Week</th>
                            <?php elseif ($report_type === 'monthly'): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <?php elseif ($report_type === 'staff'): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <?php elseif ($report_type === 'pump'): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                            <?php elseif ($report_type === 'shift'): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                            <?php endif; ?>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Amount</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Collected Amount</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Difference</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Difference %</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <?php if ($report_type === 'daily'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= date('d M Y', strtotime($row['date'])) ?>
                            </td>
                            <?php elseif ($report_type === 'weekly'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= date('d M', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?>
                            </td>
                            <?php elseif ($report_type === 'monthly'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= $row['month_name'] ?>
                            </td>
                            <?php elseif ($report_type === 'staff'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($row['staff_name']) ?>
                            </td>
                            <?php elseif ($report_type === 'pump'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($row['pump_name']) ?>
                            </td>
                            <?php elseif ($report_type === 'shift'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= $row['shift_name'] ?>
                            </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                <?= $row['count'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                <?= $currency_symbol ?> <?= number_format($row['expected'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                <?= $currency_symbol ?> <?= number_format($row['collected'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right
                                      <?= $row['difference'] > 0 ? 'text-green-600' : ($row['difference'] < 0 ? 'text-red-600' : 'text-gray-500') ?>">
                                <?= $currency_symbol ?> <?= number_format($row['difference'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right
                                      <?= $row['difference'] > 0 ? 'text-green-600' : ($row['difference'] < 0 ? 'text-red-600' : 'text-gray-500') ?>">
                                <?php
                                $diff_percentage = 0;
                                if ($row['expected'] > 0) {
                                    $diff_percentage = ($row['difference'] / $row['expected']) * 100;
                                }
                                echo number_format($diff_percentage, 2) . '%';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="bg-gray-50 font-bold">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                Totals
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?= $total_records ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?= $currency_symbol ?> <?= number_format($total_expected, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?= $currency_symbol ?> <?= number_format($total_collected, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right
                                  <?= $total_difference > 0 ? 'text-green-600' : ($total_difference < 0 ? 'text-red-600' : 'text-gray-900') ?>">
                                <?= $currency_symbol ?> <?= number_format($total_difference, 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right
                                  <?= $total_difference > 0 ? 'text-green-600' : ($total_difference < 0 ? 'text-red-600' : 'text-gray-900') ?>">
                                <?php
                                $total_diff_percentage = 0;
                                if ($total_expected > 0) {
                                    $total_diff_percentage = ($total_difference / $total_expected) * 100;
                                }
                                echo number_format($total_diff_percentage, 2) . '%';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Add custom JavaScript for the page -->
<script>
    // Initialize chart when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        const chartData = <?= $chart_data_json ?>;
        const ctx = document.getElementById('reportChart').getContext('2d');
        const currencySymbol = '<?= $currency_symbol ?>';
        
        // Extract data for the chart
        const labels = chartData.map(item => item.label);
        const expectedData = chartData.map(item => item.expected);
        const collectedData = chartData.map(item => item.collected);
        const differenceData = chartData.map(item => item.difference);
        
        // Create chart
        const myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Expected',
                        data: expectedData,
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Collected',
                        data: collectedData,
                        backgroundColor: 'rgba(16, 185, 129, 0.5)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += currencySymbol + ' ' + context.raw.toFixed(2);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return currencySymbol + ' ' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    });
    
    // Print report function
    function printReport() {
        const reportContainer = document.getElementById('report-container');
        const printContents = reportContainer.innerHTML;
        const originalContents = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="padding: 20px;">
                <h1 style="text-align: center; margin-bottom: 20px;">Cash Settlement Report</h1>
                ${printContents}
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    }
    
    // Export to CSV function
    function exportCSV() {
        const reportType = '<?= $report_type ?>';
        let header = ['Records', 'Expected Amount', 'Collected Amount', 'Difference', 'Difference %'];
        
        if (reportType === 'daily') {
            header.unshift('Date');
        } else if (reportType === 'weekly') {
            header.unshift('Week');
        } else if (reportType === 'monthly') {
            header.unshift('Month');
        } else if (reportType === 'staff') {
            header.unshift('Staff');
        } else if (reportType === 'pump') {
            header.unshift('Pump');
        } else if (reportType === 'shift') {
            header.unshift('Shift');
        }
        
        const reportData = <?= json_encode($report_data) ?>;
        const currencySymbol = '<?= $currency_symbol ?>';
        
        let csvContent = header.join(',') + '\n';
        
        reportData.forEach(row => {
            let label = '';
            
            if (reportType === 'daily') {
                const date = new Date(row.date);
                label = date.toLocaleDateString();
            } else if (reportType === 'weekly') {
                label = row.week;
            } else if (reportType === 'monthly') {
                label = row.month_name;
            } else if (reportType === 'staff') {
                label = row.staff_name;
            } else if (reportType === 'pump') {
                label = row.pump_name;
            } else if (reportType === 'shift') {
                label = row.shift_name;
            }
            
            const diffPercentage = row.expected > 0 ? (row.difference / row.expected) * 100 : 0;
            
            const rowData = [
                label,
                row.count,
                row.expected.toFixed(2),
                row.collected.toFixed(2),
                row.difference.toFixed(2),
                diffPercentage.toFixed(2) + '%'
            ];
            
            csvContent += rowData.join(',') + '\n';
        });
        
        // Add totals row
        const totalExpected = <?= $total_expected ?>;
        const totalCollected = <?= $total_collected ?>;
        const totalDifference = <?= $total_difference ?>;
        const totalRecords = <?= $total_records ?>;
        const totalDiffPercentage = totalExpected > 0 ? (totalDifference / totalExpected) * 100 : 0;
        
        const totalsRow = [
            'Totals',
            totalRecords,
            totalExpected.toFixed(2),
            totalCollected.toFixed(2),
            totalDifference.toFixed(2),
            totalDiffPercentage.toFixed(2) + '%'
        ];
        
        csvContent += totalsRow.join(',') + '\n';
        
        // Create and download CSV file
        const encodedUri = encodeURI('data:text/csv;charset=utf-8,' + csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'cash_settlement_report.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>