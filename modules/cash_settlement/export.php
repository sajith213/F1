<?php
/**
 * Cash Settlement Module - Export Data
 * 
 * This file handles the export of cash settlement data in various formats (CSV, Excel)
 */

// Start session if not already started
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Include required files
require_once '../../includes/db.php';
require_once 'functions.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to export cash settlement data.'
    ]);
    exit;
}

// Get export parameters
$export_type = isset($_GET['type']) ? $_GET['type'] : 'csv';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$pump_id = isset($_GET['pump_id']) ? intval($_GET['pump_id']) : 0;
$shift = isset($_GET['shift']) ? $_GET['shift'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Prepare filters for the data
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

// Get currency symbol from settings
$currency_symbol = 'LKR'; // Default
$settings_query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$settings_result = $conn->query($settings_query);
if ($settings_result && $settings_row = $settings_result->fetch_assoc()) {
    $currency_symbol = $settings_row['setting_value'];
}

// Get all records based on filters
$all_records = getCashRecords($filters, 1, 10000)['records']; // Get up to 10000 records for export

// Prepare data based on report type
$export_data = [];

if ($report_type === 'detailed') {
    // Detailed records - use all records directly
    $export_data = $all_records;
} else {
    // Summarized data
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
        $export_data = array_values($by_date);
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
        
        $export_data = array_values($by_week);
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
        $export_data = array_values($by_month);
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
        
        $export_data = array_values($by_staff);
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
        
        $export_data = array_values($by_pump);
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
        
        $export_data = array_values($by_shift);
    }
}

// Calculate totals for summary reports
$total_expected = 0;
$total_collected = 0;
$total_difference = 0;
$total_records = 0;

if ($report_type !== 'detailed') {
    foreach ($export_data as $row) {
        $total_expected += isset($row['expected']) ? $row['expected'] : 0;
        $total_collected += isset($row['collected']) ? $row['collected'] : 0;
        $total_difference += isset($row['difference']) ? $row['difference'] : 0;
        $total_records += isset($row['count']) ? $row['count'] : 0;
    }
}

// Determine file name
$timestamp = date('Ymd_His');
$filename = 'cash_settlement_' . $report_type . '_' . $timestamp;

// Handle different export types
if ($export_type === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM (for Excel to recognize UTF-8)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write CSV headers based on report type
    if ($report_type === 'detailed') {
        // Detailed report headers
        fputcsv($output, [
            'ID', 'Date', 'Staff', 'Pump', 'Shift', 
            'Expected Amount (' . $currency_symbol . ')', 
            'Collected Amount (' . $currency_symbol . ')', 
            'Difference (' . $currency_symbol . ')', 
            'Status', 'Verified By', 'Verification Date'
        ]);
        
        // Write data rows
        foreach ($export_data as $record) {
            fputcsv($output, [
                $record['record_id'],
                $record['record_date'],
                $record['first_name'] . ' ' . $record['last_name'],
                $record['pump_name'],
                ucfirst($record['shift']),
                number_format($record['expected_amount'], 2),
                number_format($record['collected_amount'], 2),
                number_format($record['difference'], 2),
                ucfirst($record['status']),
                $record['verifier_name'] ?? 'N/A',
                $record['verification_date'] ?? 'N/A'
            ]);
        }
    } else {
        // Summary report headers
        $headers = [
            'Records', 
            'Expected Amount (' . $currency_symbol . ')', 
            'Collected Amount (' . $currency_symbol . ')', 
            'Difference (' . $currency_symbol . ')',
            'Difference (%)'
        ];
        
        if ($report_type === 'daily') {
            array_unshift($headers, 'Date');
        } elseif ($report_type === 'weekly') {
            array_unshift($headers, 'Week');
        } elseif ($report_type === 'monthly') {
            array_unshift($headers, 'Month');
        } elseif ($report_type === 'staff') {
            array_unshift($headers, 'Staff');
        } elseif ($report_type === 'pump') {
            array_unshift($headers, 'Pump');
        } elseif ($report_type === 'shift') {
            array_unshift($headers, 'Shift');
        }
        
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($export_data as $row) {
            $label = '';
            
            if ($report_type === 'daily') {
                $label = date('d M Y', strtotime($row['date']));
            } elseif ($report_type === 'weekly') {
                $label = date('d M Y', strtotime($row['start_date'])) . ' - ' . date('d M Y', strtotime($row['end_date']));
            } elseif ($report_type === 'monthly') {
                $label = $row['month_name'];
            } elseif ($report_type === 'staff') {
                $label = $row['staff_name'];
            } elseif ($report_type === 'pump') {
                $label = $row['pump_name'];
            } elseif ($report_type === 'shift') {
                $label = $row['shift_name'];
            }
            
            $diff_percentage = 0;
            if ($row['expected'] > 0) {
                $diff_percentage = ($row['difference'] / $row['expected']) * 100;
            }
            
            $data_row = [
                $row['count'],
                number_format($row['expected'], 2),
                number_format($row['collected'], 2),
                number_format($row['difference'], 2),
                number_format($diff_percentage, 2) . '%'
            ];
            
            array_unshift($data_row, $label);
            
            fputcsv($output, $data_row);
        }
        
        // Add totals row
        $total_diff_percentage = 0;
        if ($total_expected > 0) {
            $total_diff_percentage = ($total_difference / $total_expected) * 100;
        }
        
        $totals_row = [
            $total_records,
            number_format($total_expected, 2),
            number_format($total_collected, 2),
            number_format($total_difference, 2),
            number_format($total_diff_percentage, 2) . '%'
        ];
        
        array_unshift($totals_row, 'TOTALS');
        
        fputcsv($output, $totals_row);
    }
    
    fclose($output);
    exit;
} elseif ($export_type === 'excel') {
    // Here you would generate an Excel file
    // For this you would need a library like PhpSpreadsheet
    // Since we haven't included that in this implementation, we'll return JSON instead
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Excel export not implemented yet. Please use CSV export.'
    ]);
    exit;
} else {
    // Unknown export type
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid export type specified.'
    ]);
    exit;
}
?>