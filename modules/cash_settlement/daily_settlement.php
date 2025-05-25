<?php
/**
 * Cash Settlement Module - Daily Settlement
 * This page allows users to create and manage daily cash settlements
 */
ob_start();
// Set page title and include header
$page_title = "Daily Cash Settlement";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Cash Settlement</a> / Daily Settlement';
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once 'hooks.php'; // Include hooks for credit management integration
require_once 'functions.php'; // Contains createCashRecord(), getStaffAssignments(), etc.

// Check if user has permission
if (!has_permission('manage_cash')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access the cash settlement module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get current date or use provided date
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Check for existing verified settlements
function checkForVerifiedSettlements() {
    global $conn, $date;
    
    // If we're processing a form submission, don't redirect
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }
    
    // Get all assignments for this date
    $assignments_query = "SELECT staff_id, pump_id, LOWER(shift) as shift 
                         FROM staff_assignments 
                         WHERE assignment_date = ?";
    $assign_stmt = $conn->prepare($assignments_query);
    $assign_stmt->bind_param("s", $date);
    $assign_stmt->execute();
    $assignments_result = $assign_stmt->get_result();
    
    // If no assignments, return early
    if ($assignments_result->num_rows == 0) {
        return;
    }
    
    $all_processed = true;
    $assignments_count = 0;
    
    // Check each assignment for a matching processed record
    while ($assignment = $assignments_result->fetch_assoc()) {
        $assignments_count++;
        
        // Look for a matching record that's been processed
        $record_query = "SELECT status FROM daily_cash_records 
                         WHERE record_date = ? 
                         AND staff_id = ? 
                         AND pump_id = ? 
                         AND LOWER(shift) = ?";
        $record_stmt = $conn->prepare($record_query);
        $record_stmt->bind_param("ssis", $date, $assignment['staff_id'], 
                               $assignment['pump_id'], $assignment['shift']);
        $record_stmt->execute();
        $record_result = $record_stmt->get_result();
        
        // If no record exists or it's still pending, we're not done
        if ($record_result->num_rows == 0) {
            $all_processed = false;
            break;
        }
        
        $record = $record_result->fetch_assoc();
        if ($record['status'] == 'pending') {
            $all_processed = false;
            break;
        }
    }
    
    // Only show the message if we have assignments and all are processed
    if ($assignments_count > 0 && $all_processed) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">All Settlements Completed</p>
                <p>All cash settlements for this date have been fully processed and verified.</p>
              </div>';
    }
}
// Check if form was submitted
$success_message = $error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_settlement'])) {
    $required_fields = ['staff_id', 'pump_shifts', 'meter_expected_amounts', 'fuel_prices', 'collected_cash', 'collected_card', 'collected_credit']; // test_liters is optional
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (in_array($field, ['pump_shifts', 'meter_expected_amounts', 'fuel_prices', 'collected_cash', 'collected_card', 'collected_credit'])) {
            if (!isset($_POST[$field]) || !is_array($_POST[$field]) || empty($_POST[$field]) || count(array_filter($_POST[$field], 'strlen')) === 0) {
                $missing_fields[] = $field;
            }
        } elseif (!isset($_POST[$field]) || $_POST[$field] === '') {
            $missing_fields[] = $field;
        }
    }

    if (!isset($_POST['test_liters']) || !is_array($_POST['test_liters'])) {
        $missing_fields[] = 'test_liters';
    }

    if (!empty($missing_fields)) {
        $error_message = "Please fill in all required fields: " . implode(', ', $missing_fields);
    } else {
        $staff_id = $_POST['staff_id'];
        $record_date = $_POST['record_date'];
        $pump_shifts_posted = $_POST['pump_shifts']; // Renamed to avoid confusion
        $meter_expected_amounts = $_POST['meter_expected_amounts'];
        $collected_cash_amounts = $_POST['collected_cash'];
        $collected_card_amounts = $_POST['collected_card'];
        $collected_credit_amounts = $_POST['collected_credit'];

        $credit_customer_ids_posted = isset($_POST['credit_customer_ids']) ? $_POST['credit_customer_ids'] : [];
        $credit_amounts_posted = isset($_POST['credit_amounts']) ? $_POST['credit_amounts'] : [];

        $test_liters_amounts = $_POST['test_liters'];
        $fuel_prices = $_POST['fuel_prices'];

        // ADD THIS DUPLICATE CHECK CODE HERE
$duplicates_found = false;

foreach ($pump_shifts_posted as $pump_shift_value) {
    if (empty($pump_shift_value)) continue;
    list($pump_id, $shift_from_form) = explode('|', $pump_shift_value);
    $processed_shift = strtolower(trim($shift_from_form));
    
    // Check if this combination already exists
    $check_query = "SELECT COUNT(*) as count FROM daily_cash_records 
                    WHERE record_date = ? AND staff_id = ? AND pump_id = ? AND shift = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ssis", $record_date, $staff_id, $pump_id, $processed_shift);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        $duplicates_found = true;
        $error_message = "Some pump/shifts have already been processed. Please refresh the page.";
        break;
    }
}

if ($duplicates_found) {
    // Don't process the form further - just display the error message
} else 

        $success_count = 0;
        $error_count = 0;
        $created_record_ids = [];

        foreach ($pump_shifts_posted as $index => $pump_shift_value) {
            if (empty($pump_shift_value)) continue;

            list($pump_id, $shift_from_form) = explode('|', $pump_shift_value);
            
            // Ensure shift is lowercase and trimmed for consistency with DB ENUM and comparison
            $processed_shift = strtolower(trim($shift_from_form));

            $meter_expected = floatval($meter_expected_amounts[$index]);
            $collected_cash = floatval($collected_cash_amounts[$index]);
            $collected_card = floatval($collected_card_amounts[$index]);
            $collected_credit = floatval($collected_credit_amounts[$index]);
            $test_liters = isset($test_liters_amounts[$index]) ? floatval($test_liters_amounts[$index]) : 0;
            $fuel_price = floatval($fuel_prices[$index]);

            $credit_entries = [];
            if ($collected_credit > 0) {
                if (isset($credit_customer_ids_posted[$index]) && isset($credit_amounts_posted[$index])) {
                    $total_credit_entries = 0;
                    for ($i = 0; $i < count($credit_customer_ids_posted[$index]); $i++) {
                        if (!empty($credit_customer_ids_posted[$index][$i]) && $credit_amounts_posted[$index][$i] > 0) {
                            $credit_entries[] = [
                                'customer_id' => intval($credit_customer_ids_posted[$index][$i]),
                                'amount' => floatval($credit_amounts_posted[$index][$i])
                            ];
                            $total_credit_entries += floatval($credit_amounts_posted[$index][$i]);
                        }
                    }
                    if (abs($total_credit_entries - $collected_credit) > 0.01) {
                        $error_message .= " Total credit amount doesn't match individual entries for one of the shifts.";
                        $error_count++;
                        continue;
                    }
                    if (empty($credit_entries) && $collected_credit > 0) {
                        $error_message .= " Credit amount entered but no valid customers for one of the shifts.";
                        $error_count++;
                        continue;
                    }
                } else {
                    $error_message .= " Credit amount entered but no customer data for one of the shifts.";
                    $error_count++;
                    continue;
                }
            }

            $test_value = $test_liters * $fuel_price;
            $adjusted_expected = $meter_expected - $test_value;
            $total_collected = $collected_cash + $collected_card + $collected_credit;

            $data = [
                'record_date' => $record_date,
                'staff_id' => $staff_id,
                'pump_id' => $pump_id,
                'shift' => $processed_shift, // Use processed shift
                'meter_expected_amount' => $meter_expected, //This will be 'expected_amount' in DB based on your SQL
                'test_liters' => $test_liters,
                'fuel_price_at_time' => $fuel_price,
                'test_value' => $test_value,
                'adjusted_expected_amount' => $adjusted_expected,
                'collected_cash' => $collected_cash,
                'collected_card' => $collected_card,
                'collected_credit' => $collected_credit,
                'credit_entries' => $credit_entries,
                'total_collected' => $total_collected,
                // If this record is verified during creation, set status to verified
                'status' => $meter_expected > 0 ? 'verified' : 'pending',
                'verified_by' => $meter_expected > 0 ? $_SESSION['user_id'] : null,
                'verification_date' => $meter_expected > 0 ? date('Y-m-d H:i:s') : null
            ];

            $record_id_created = createCashRecord($data); // Renamed variable
            // Add this after $record_id_created = createCashRecord($data);
if ($record_id_created && $test_liters > 0) {
    // Try to record the test liters adjustment
    recordTestLitersAdjustment($pump_id, $test_liters, $record_id_created);
}

            if ($record_id_created) {
                $success_count++;
                $created_record_ids[] = $record_id_created;
            } else {
                error_log("Failed to create cash record for pump $pump_id, shift $processed_shift. Data: " . print_r($data, true));
                $error_count++;
            }
        }

        if ($success_count > 0) {
            if ($success_count == 1 && $error_count == 0) {
                header("Location: settlement_details.php?id=" . $created_record_ids[0] . "&success=1");
                exit;
            } else {
                $success_message = "Created $success_count cash settlement record(s) successfully.";
                if ($error_count > 0) {
                    $error_message .= " Failed to create $error_count cash settlement record(s). Check system logs for details.";
                }
            }
        } else {
             if (empty($error_message)) { // If no specific errors were set during loop
                $error_message = "Failed to create any cash settlement records. Check system logs for details.";
            }
        }
    }
}

// Get staff assignments for the selected date
$assignments = getStaffAssignments($date);

// Get all staff and pumps for dropdowns
$all_staff = getAllStaff();
$all_pumps = getAllPumps();

// Get existing cash records for this date
// Ensure getCashRecords returns 'expected_amount' and 'status' from your daily_cash_records table
$existing_records_data = getCashRecords(['date_from' => $date, 'date_to' => $date]);
$cash_records = isset($existing_records_data['records']) ? $existing_records_data['records'] : [];

// Count statuses for dashboard
$pending_count = 0;
$processed_count = 0;
$verified_count = 0;
$total_count = count($assignments);

if (is_array($cash_records) && !empty($cash_records)) {
    foreach ($cash_records as $record) {
        if ($record['status'] == 'pending') {
            $pending_count++;
        } else if ($record['status'] == 'verified') {
            $verified_count++;
        } else {
            $processed_count++;
        }
    }
}

// Check for verified settlements after loading records
checkForVerifiedSettlements();

// Get currency symbol from settings
$currency_symbol = 'Rs.'; // Default

$stmt_currency = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt_currency) {
    $stmt_currency->execute();
    $result_currency = $stmt_currency->get_result();
    if ($row_currency = $result_currency->fetch_assoc()) {
        $currency_symbol = $row_currency['setting_value'];
    }
    $stmt_currency->close();
}

// Organize assignments by staff for easier access via JavaScript
$staff_assignments = [];
foreach ($assignments as $assignment_item) { // Renamed to avoid conflict
    $staff_id_js = $assignment_item['staff_id'];
    $pump_id_js = $assignment_item['pump_id'];
    $shift_js = strtolower(trim($assignment_item['shift'])); // Ensure shift is lowercase for JS consistency

    if (!isset($staff_assignments[$staff_id_js])) {
        $staff_assignments[$staff_id_js] = [];
    }

    $staff_assignments[$staff_id_js][] = [
        'pump_id' => $pump_id_js,
        'pump_name' => $assignment_item['pump_name'],
        'shift' => $shift_js, // Store lowercase, trimmed shift for JS
    ];
}
?>

<style>
.verified-pump {
    border-left: 4px solid #10B981; /* Green border */
    background-color: rgba(16, 185, 129, 0.05); /* Light green background */
}
</style>

<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <h2 class="text-lg font-semibold text-gray-700 mb-4">Select Settlement Date</h2>
    <form method="GET" action="daily_settlement.php" class="flex flex-wrap items-end gap-4">
        <div class="w-full sm:w-auto">
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                   max="<?= date('Y-m-d') ?>">
        </div>
        <div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                <i class="fas fa-calendar-day mr-2"></i> Load Date
            </button>
        </div>
        <div class="ml-auto text-right">
            <a href="daily_settlement.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                <i class="fas fa-sync-alt mr-2"></i> Today
            </a>
        </div>
    </form>
</div>

<?php if ($success_message): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
    <p class="font-bold">Success</p>
    <p><?= htmlspecialchars($success_message) ?></p>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
    <p class="font-bold">Error</p>
    <p><?= htmlspecialchars($error_message) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow-md p-4">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Staff Assignments (<?= date('d M Y', strtotime($date)) ?>)</h2>
        
        <!-- Settlement Status Dashboard -->
        <div class="bg-gray-100 p-4 mb-4 rounded-lg">
            <div class="grid grid-cols-4 gap-4 text-center">
                <div>
                    <span class="text-lg font-bold"><?= $total_count ?></span>
                    <p class="text-sm text-gray-600">Total Assignments</p>
                </div>
                <div>
                    <span class="text-lg font-bold text-yellow-600"><?= $pending_count ?></span>
                    <p class="text-sm text-gray-600">Pending</p>
                </div>
                <div>
                    <span class="text-lg font-bold text-blue-600"><?= $processed_count ?></span>
                    <p class="text-sm text-gray-600">Processed</p>
                </div>
                <div>
                    <span class="text-lg font-bold text-green-600"><?= $verified_count ?></span>
                    <p class="text-sm text-gray-600">Verified</p>
                </div>
            </div>
        </div>
        
        <?php if (empty($assignments)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
            <p>No staff assignments found for <?= date('d M Y', strtotime($date)) ?>.</p>
            <p class="mt-2">You can <a href="../../modules/staff_management/assign_staff.php?date=<?= urlencode($date) ?>" class="text-blue-600 hover:underline">create staff assignments</a> for this date or select a different date.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Verification</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="assignmentsTable">
                    <?php foreach ($assignments as $index => $assignment_row): // Renamed variable
                        // Initialize for each assignment
                        $settlement_exists = false;
                        $is_verified = false; // CRITICAL: Initialize $is_verified
                        $record_id_display = null; // Renamed variable

                        // Prepare assignment data for comparison (lowercase shift)
                        $current_assignment_staff_id = trim($assignment_row['staff_id']);
                        $current_assignment_pump_id = trim($assignment_row['pump_id']);
                        $current_assignment_shift = strtolower(trim($assignment_row['shift']));

                        if (is_array($cash_records) && !empty($cash_records)) {
                            foreach ($cash_records as $record) {
                                $record_staff_id = trim($record['staff_id']);
                                $record_pump_id = trim($record['pump_id']);
                                // Assuming $record['shift'] from DB is already lowercase (due to ENUM)
                                $record_shift = strtolower(trim($record['shift']));


                                if ($record_staff_id == $current_assignment_staff_id &&
                                    $record_pump_id == $current_assignment_pump_id &&
                                    $record_shift == $current_assignment_shift) {

                                    $settlement_exists = true;
                                    $record_id_display = $record['record_id']; // Use the specific record_id

                                    // Determine if verified based on the 'status' field from daily_cash_records
                                    // Ensure your getCashRecords() function fetches the 'status' field.
                                    if (isset($record['status']) && $record['status'] === 'verified') {
                                        $is_verified = true;
                                    }
                                    break; // Match found for this assignment
                                }
                            }
                        }
                    ?>
                    <tr id="assignment-row-<?= $index ?>" class="<?= $is_verified ? 'bg-green-50' : '' ?>">
                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900"><?= htmlspecialchars($assignment_row['staff_name']) ?></div></td>
                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900"><?= htmlspecialchars($assignment_row['pump_name']) ?></div></td>
                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-900"><?= ucfirst($current_assignment_shift) // Display processed shift ?></div></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php if ($settlement_exists): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Processed</span>
                            <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center" id="verify-status-<?= $index ?>">
                            <?php if ($is_verified): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <i class="fas fa-check-circle mr-1"></i> Verified
                            </span>
                            <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Not Verified</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <?php if ($settlement_exists): ?>
                                <?php if ($record_id_display): // Make sure we have a record ID to link to ?>
                                <a href="settlement_details.php?id=<?= $record_id_display ?>" class="text-blue-600 hover:text-blue-900 mr-2">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php endif; ?>
                                <?php if (!$is_verified && $record_id_display): // Can only verify if a record exists ?>
                                <button class="verify-btn text-orange-600 hover:text-orange-900"
                                        data-staff="<?= htmlspecialchars($current_assignment_staff_id) ?>"
                                        data-pump="<?= htmlspecialchars($current_assignment_pump_id) ?>"
                                        data-shift="<?= htmlspecialchars($current_assignment_shift) // Use processed shift for data attribute ?>"
                                        data-record="<?= $record_id_display ?>"
                                        data-row="<?= $index ?>">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                                <?php endif; ?>
                            <?php else: ?>
                            <button onclick="loadStaff(<?= htmlspecialchars($current_assignment_staff_id) ?>)" class="text-green-600 hover:text-green-900">
                                <i class="fas fa-plus-circle"></i> Process
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow-md p-4">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Create Cash Settlement</h2>
        <form id="settlement-form" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?date=<?= urlencode($date) // urlencode date ?>" class="space-y-4">
            <input type="hidden" name="record_date" value="<?= htmlspecialchars($date) ?>">
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                <select id="staff_id" name="staff_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <option value="">Select Staff Member</option>
                    <?php if (is_array($all_staff)): ?>
                        <?php foreach ($all_staff as $staff_member): // Renamed variable
                            // Only show staff with assignments ON THIS DATE
                            if (isset($staff_assignments[$staff_member['staff_id']])):
                        ?>
                        <option value="<?= $staff_member['staff_id'] ?>"><?= htmlspecialchars($staff_member['full_name']) ?></option>
                        <?php
                            endif;
                        endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div id="pump-shift-container" class="space-y-6">
                <p class="text-sm text-gray-500">Select a staff member to see their assigned pumps and shifts.</p>
            </div>

            <div id="add-more-container" class="hidden pt-2">
                <button type="button" id="add-more-btn" class="text-blue-600 hover:text-blue-800 flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i> Add Another Pump/Shift
                </button>
            </div>

            <div>
                <button type="submit" name="submit_settlement" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-save mr-2"></i> Save Settlement
                </button>
            </div>
        </form>
    </div>
</div>

<div id="meter-readings-modal" class="hidden fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-tachometer-alt text-blue-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Meter Readings & Expected Sales
                        </h3>
                        <div class="mt-4">
                            <div id="meter-readings-content" class="overflow-x-auto text-sm">
                                <p class="text-center text-gray-500 py-4">Loading meter readings...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="use-readings-btn" disabled class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                    Use This Expected Amount
                </button>
                <button type="button" id="close-modal-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Store staff assignments data for use in JavaScript
    const staffAssignments = <?= json_encode($staff_assignments) ?>; // Uses lowercase shifts from PHP
    const currentDate = "<?= htmlspecialchars($date) ?>";
    const currencySymbol = "<?= htmlspecialchars($currency_symbol) ?>";
    let currentPumpShiftIndex = 0;
    let creditCustomers = [];

    window.addEventListener('DOMContentLoaded', function() {
        fetchCreditCustomers();

        const verifyButtons = document.querySelectorAll('.verify-btn');
        verifyButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const pumpId = this.dataset.pump;
                const shift = this.dataset.shift; // Will be lowercase from data-shift attribute
                const recordId = this.dataset.record;
                const rowIndex = this.dataset.row; // This is the $index from the PHP loop

                showVerificationModal(pumpId, currentDate, rowIndex, shift, recordId);
            });
        });

        document.getElementById('settlement-form').addEventListener('submit', function(e) {
            const pumpBlocks = document.querySelectorAll('[id^="pump-shift-block-"]');
            let unverifiedPumps = 0;
            pumpBlocks.forEach(block => {
                const index = block.id.replace('pump-shift-block-', '');
                const meterExpectedInput = document.getElementById(`meter-expected-amount-${index}`);
                if (!meterExpectedInput || parseFloat(meterExpectedInput.value) <= 0) {
                    unverifiedPumps++;
                }
            });
            if (unverifiedPumps > 0) {
                if (!confirm(`Warning: ${unverifiedPumps} pump(s) have not been verified with meter readings. Continue anyway?`)) {
                    e.preventDefault();
                }
            }
        });
    });

    function fetchCreditCustomers() {
        fetch('get_credit_customers.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error("Error fetching credit customers:", data.error);
                    return;
                }
                creditCustomers = data.customers;
            })
            .catch(error => {
                console.error("Error fetching credit customers:", error);
            });
    }

    function updateVerificationStatus(rowIndex, isVerified = true) {
        const statusCell = document.getElementById(`verify-status-${rowIndex}`);
        if (statusCell) {
            if (isVerified) {
                statusCell.innerHTML = `
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                        <i class="fas fa-check-circle mr-1"></i> Verified
                    </span>`;
                const row = document.getElementById(`assignment-row-${rowIndex}`);
                row.classList.add('bg-green-50');
                const verifyBtn = row.querySelector('.verify-btn');
                if (verifyBtn) {
                    verifyBtn.classList.add('hidden');
                }
            } else {
                 statusCell.innerHTML = `
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                        Not Verified
                    </span>`;
            }
        }
    }

    function addCreditEntry(index, customerId = '', amount = '') {
        const container = document.getElementById(`credit-entries-${index}`);
        const entryId = `credit-entry-${index}-${Date.now()}`;
        const entryHtml = `
            <div id="${entryId}" class="credit-entry grid grid-cols-12 gap-2 items-center border border-gray-200 rounded p-2">
                <div class="col-span-7">
                    <select name="credit_customer_ids[${index}][]" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">Select Customer</option>
                    </select>
                </div>
                <div class="col-span-4 relative">
                    <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                    </div>
                    <input type="number" name="credit_amounts[${index}][]" step="0.01" min="0" value="${amount}"
                           class="credit-amount-input pl-6 block w-full rounded-md" required
                           data-index="${index}" placeholder="Amount">
                </div>
                <div class="col-span-1 text-center">
                    <button type="button" class="remove-credit-entry-btn text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', entryHtml);
        const entryElement = document.getElementById(entryId);
        const selectElement = entryElement.querySelector('select');
        populateCreditCustomers(selectElement);
        if (customerId) {
            selectElement.value = customerId;
        }
        const amountInput = entryElement.querySelector('.credit-amount-input');
        amountInput.addEventListener('input', () => updateTotalCredit(index));
        const removeBtn = entryElement.querySelector('.remove-credit-entry-btn');
        removeBtn.addEventListener('click', function() {
            entryElement.remove();
            updateTotalCredit(index);
        });
        updateTotalCredit(index);
    }

    function updateTotalCredit(index) {
        const container = document.getElementById(`credit-entries-${index}`);
        const amountInputs = container.querySelectorAll('.credit-amount-input');
        const totalDisplay = document.getElementById(`total-credit-${index}`);
        const collectedCreditInput = document.getElementById(`collected-credit-${index}`);
        let total = 0;
        amountInputs.forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        totalDisplay.textContent = total.toFixed(2);
        collectedCreditInput.value = total.toFixed(2);
        updateSettlementCalculation(index);
    }

    function updateSettlementCalculation(index) {
    const meterExpectedInput = document.getElementById(`meter-expected-amount-${index}`);
    const testLitersInput = document.getElementById(`test-liters-${index}`);
    const collectedCashInput = document.getElementById(`collected-cash-${index}`);
    const collectedCardInput = document.getElementById(`collected-card-${index}`);
    const collectedCreditInput = document.getElementById(`collected-credit-${index}`);
    const totalCollectedDisplay = document.getElementById(`total-collected-${index}`);
    const differenceDisplay = document.getElementById(`difference-${index}`);
    const differenceStatus = document.getElementById(`difference-status-${index}`);
    const fuelPriceInput = document.getElementById(`fuel-price-${index}`);
    const creditEntriesContainer = document.getElementById(`credit-entries-container-${index}`);

    const meterExpected = parseFloat(meterExpectedInput.value) || 0;
    const testLiters = parseFloat(testLitersInput.value) || 0;
    const collectedCash = parseFloat(collectedCashInput.value) || 0;
    const collectedCard = parseFloat(collectedCardInput.value) || 0;
    const collectedCredit = parseFloat(collectedCreditInput.value) || 0;
    const fuelPrice = parseFloat(fuelPriceInput.value) || 0;

    const testValue = testLiters * fuelPrice;
    const adjustedExpected = meterExpected - testValue;
    const totalCollected = collectedCash + collectedCard + collectedCredit;
    
    // MODIFIED: Adjust the difference calculation to account for test liters
    // When comparing collected vs expected, subtract test value from both sides
    // This ensures test liters don't affect the cash balance
    const difference = totalCollected - meterExpected;

    totalCollectedDisplay.value = totalCollected.toFixed(2);
    differenceDisplay.value = difference.toFixed(2);

    if (meterExpected > 0) {
        creditEntriesContainer.classList.remove('hidden');
    } else {
        creditEntriesContainer.classList.add('hidden');
    }

    differenceStatus.textContent = '';
    differenceStatus.className = 'mt-1 text-xs';
    if (testValue > 0) {
        differenceStatus.innerHTML += `(Meter: ${meterExpected.toFixed(2)}, Test Ded.: ${testValue.toFixed(2)}, Adj. Expected: ${adjustedExpected.toFixed(2)})<br>`;
    }
    if (difference > 0.01) {
        differenceStatus.innerHTML += `<span class="text-green-600 font-semibold">Excess: ${currencySymbol} ${difference.toFixed(2)}</span>`;
    } else if (difference < -0.01) {
        differenceStatus.innerHTML += `<span class="text-red-600 font-semibold">Shortage: ${currencySymbol} ${Math.abs(difference).toFixed(2)}</span>`;
    } else {
        differenceStatus.innerHTML += `<span class="text-gray-600">Balanced</span>`;
    }
    const testValueDisplay = document.getElementById(`test-value-${index}`);
    if(testValueDisplay) {
        testValueDisplay.textContent = `Value: ${currencySymbol} ${testValue.toFixed(2)} (at ${currencySymbol}${fuelPrice.toFixed(2)}/L)`;
    }
}
    function populateCreditCustomers(selectElement) {
        while (selectElement.options.length > 1) {
            selectElement.remove(1);
        }
        creditCustomers.forEach(customer => {
            const option = document.createElement('option');
            option.value = customer.customer_id;
            const availableCredit = customer.credit_limit - customer.current_balance;
            option.text = `${customer.customer_name} (Available: ${currencySymbol}${availableCredit.toFixed(2)})`;
            if (availableCredit <= 0) {
                option.disabled = true;
            }
            selectElement.add(option);
        });
    }

    function addPumpShiftSelection(assignmentsForStaff) { // Renamed parameter
        const pumpShiftContainer = document.getElementById('pump-shift-container');
        if (currentPumpShiftIndex === 0) {
            pumpShiftContainer.innerHTML = '';
        }
        const index = currentPumpShiftIndex++;
        const container = document.createElement('div');
        container.className = 'p-4 border border-gray-200 rounded-lg relative space-y-4';
        container.id = `pump-shift-block-${index}`;

        if (index > 0) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'absolute top-2 right-2 text-red-500 hover:text-red-700 text-lg';
            removeBtn.innerHTML = '<i class="fas fa-times-circle"></i>';
            removeBtn.title = 'Remove this entry';
            removeBtn.onclick = function() {
                container.remove();
                // Potentially re-evaluate "Add More" button visibility here if needed
            };
            container.appendChild(removeBtn);
        }

        // assignmentsForStaff contains lowercase shifts
        const html = `
            <div>
                <label for="pump-shift-${index}" class="block text-sm font-medium text-gray-700 mb-1">Pump and Shift</label>
                <select id="pump-shift-${index}" name="pump_shifts[]" required
                        class="pump-shift-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                        data-index="${index}">
                    <option value="">Select Pump and Shift</option>
                    ${assignmentsForStaff.map(a =>
                        // a.shift is already lowercase here
                        `<option value="${a.pump_id}|${a.shift}">${a.pump_name} - ${a.shift.charAt(0).toUpperCase() + a.shift.slice(1)} Shift</option>`
                    ).join('')}
                </select>
            </div>
            <input type="hidden" id="fuel-price-${index}" name="fuel_prices[]" value="0">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="meter-expected-amount-${index}" class="block text-sm font-medium text-gray-700 mb-1">Meter Expected (${currencySymbol})</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                        </div>
                        <input type="number" step="0.01" id="meter-expected-amount-${index}" name="meter_expected_amounts[]" required readonly
                               class="pl-8 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm">
                    </div>
                    <button type="button" class="meter-readings-btn mt-1 text-xs text-blue-600 hover:text-blue-800" data-index="${index}">
                        <i class="fas fa-tachometer-alt mr-1"></i> Fetch/View Meter Readings
                    </button>
                </div>
                <div>
                    <label for="test-liters-${index}" class="block text-sm font-medium text-gray-700 mb-1">Test Liters Dispensed (L)</label>
                    <input type="number" step="0.0001" min="0" id="test-liters-${index}" name="test_liters[]" value="0"
       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <p id="test-value-${index}" class="mt-1 text-xs text-gray-500"></p>
                </div>
            </div>
            <hr>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="collected-cash-${index}" class="block text-sm font-medium text-gray-700 mb-1">Collected Cash (${currencySymbol})</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                         <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="collected-cash-${index}" name="collected_cash[]" required value="0"
                               class="pl-8 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                </div>
                <div>
                    <label for="collected-card-${index}" class="block text-sm font-medium text-gray-700 mb-1">Collected Card (${currencySymbol})</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="collected-card-${index}" name="collected_card[]" required value="0"
                               class="pl-8 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    </div>
                </div>
                <div>
                    <label for="collected-credit-${index}" class="block text-sm font-medium text-gray-700 mb-1">Credit Total (${currencySymbol})</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="collected-credit-${index}" name="collected_credit[]" required value="0" readonly
                               class="pl-8 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm">
                    </div>
                </div>
            </div>
            <div id="credit-entries-container-${index}" class="mt-3 hidden">
                <div class="flex justify-between items-center">
                    <label class="block text-sm font-medium text-gray-700">Credit Customers</label>
                    <button type="button" class="add-credit-entry-btn text-blue-600 hover:text-blue-800 text-sm" data-index="${index}">
                        <i class="fas fa-plus-circle"></i> Add Credit Customer
                    </button>
                </div>
                <div id="credit-entries-${index}" class="space-y-3 mt-2"></div>
                <div class="mt-2 text-right">
                    <span class="text-sm font-medium text-gray-700">Total Credit: ${currencySymbol}</span>
                    <span id="total-credit-${index}" class="text-sm font-medium">0.00</span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="total-collected-${index}" class="block text-sm font-medium text-gray-700 mb-1">Total Collected (${currencySymbol})</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                        </div>
                        <input type="text" id="total-collected-${index}" readonly
                               class="pl-8 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm">
                    </div>
                </div>
                <div>
                    <label for="difference-${index}" class="block text-sm font-medium text-gray-700 mb-1">Difference (${currencySymbol})</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">${currencySymbol}</span>
                        </div>
                        <input type="text" id="difference-${index}" readonly
                               class="pl-8 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm difference-field">
                    </div>
                    <p id="difference-status-${index}" class="mt-1 text-xs"></p>
                </div>
            </div>`;
        container.innerHTML = html;
        pumpShiftContainer.appendChild(container);

        const pumpShiftSelect = document.getElementById(`pump-shift-${index}`);
        const meterReadingsBtn = container.querySelector('.meter-readings-btn');
        const meterExpectedInput = document.getElementById(`meter-expected-amount-${index}`); // already got
        const testLitersInput = document.getElementById(`test-liters-${index}`); // already got
        const collectedCashInput = document.getElementById(`collected-cash-${index}`); // already got
        const collectedCardInput = document.getElementById(`collected-card-${index}`); // already got
        const addCreditEntryBtn = container.querySelector('.add-credit-entry-btn');

        meterReadingsBtn.addEventListener('click', function() {
            const pumpShiftValue = pumpShiftSelect.value; // e.g., "pumpId|shift"
            if (pumpShiftValue) {
                const [pumpId, shiftValue] = pumpShiftValue.split('|'); // shiftValue is lowercase
                showMeterReadingsModal(pumpId, currentDate, this.dataset.index, shiftValue);
            } else {
                alert('Please select a pump and shift first.');
            }
        });
        addCreditEntryBtn.addEventListener('click', function() {
            addCreditEntry(this.dataset.index);
        });
        [testLitersInput, collectedCashInput, collectedCardInput, meterExpectedInput].forEach(input => {
            if (input) {
                input.addEventListener('input', () => updateSettlementCalculation(index));
                input.addEventListener('change', () => updateSettlementCalculation(index));
            }
        });
        updateSettlementCalculation(index);
    }

    const modal = document.getElementById('meter-readings-modal');
    const modalContent = document.getElementById('meter-readings-content');
    const useReadingsBtn = document.getElementById('use-readings-btn');
    const closeModalBtn = document.getElementById('close-modal-btn');

    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));

    function showVerificationModal(pumpId, date, rowIndex, shift, recordId = null) {
        modal.dataset.targetRowIndex = rowIndex; // Use a different dataset property for row index
        modal.dataset.recordId = recordId || '';
        modal.classList.remove('hidden');
        modalContent.innerHTML = '<p class="text-center text-gray-500 py-4">Loading meter readings...</p>';
        useReadingsBtn.disabled = true;
        const timestamp = new Date().getTime();
        // Shift is already lowercase here
        fetch(`get_meter_readings.php?pump_id=${pumpId}&date=${date}&shift=${shift}&_=${timestamp}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                if (!data.readings || !data.hasOwnProperty('total_expected_amount') || !data.hasOwnProperty('primary_fuel_price')) {
                    console.error("Incomplete data from get_meter_readings.php:", data);
                    throw new Error('Incomplete meter reading data from server.');
                }
                const localCurrencySymbol = data.currency_symbol || currencySymbol;
                let html = '<table class="min-w-full divide-y divide-gray-200 mb-4">';
                html += '<thead class="bg-gray-50"><tr>';
                ['Nozzle', 'Fuel'].forEach(h => html += `<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">${h}</th>`);
                ['Opening', 'Closing', 'Volume (L)', 'Price', 'Amount'].forEach(h => html += `<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">${h}</th>`);
                html += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

                if (!data.readings || data.readings.length === 0) {
                    html += '<tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">No meter readings recorded.</td></tr>';
                } else {
                    data.readings.forEach(reading => {
                        const expectedAmount = parseFloat(reading.expected_amount) || 0;
                        const unitPrice = parseFloat(reading.unit_price) || 0;
                        const volume = parseFloat(reading.volume_dispensed) || 0;
                        const opening = parseFloat(reading.opening_reading) || 0;
                        const closing = parseFloat(reading.closing_reading) || 0;
                        html += `<tr>
                                    <td class="px-3 py-2 whitespace-nowrap">${reading.nozzle_number || 'N/A'}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">${reading.fuel_name || 'N/A'}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${opening.toFixed(3)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${closing.toFixed(3)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${volume.toFixed(3)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${unitPrice.toFixed(2)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${expectedAmount.toFixed(2)}</td>
                                 </tr>`;
                    });
                }
                html += '</tbody></table>';
                const totalExpected = parseFloat(data.total_expected_amount) || 0;
                const fuelPriceForTest = parseFloat(data.primary_fuel_price) || 0;
                html += `<div class="text-right font-semibold text-base mt-2">Total Expected Amount: ${localCurrencySymbol} ${totalExpected.toFixed(2)}</div>`;
                if (fuelPriceForTest > 0) {
                    html += `<div class="text-right text-sm text-gray-600">(Fuel Price for Test Calc: ${localCurrencySymbol} ${fuelPriceForTest.toFixed(2)}/L)</div>`;
                } else {
                    html += `<div class="text-right text-sm text-red-600">(Warning: Fuel price for test calc not available!)</div>`;
                }
                modalContent.innerHTML = html;
                modalContent.dataset.totalExpected = totalExpected.toFixed(2);
                modalContent.dataset.fuelPrice = fuelPriceForTest.toFixed(2);
                useReadingsBtn.disabled = false;
                useReadingsBtn.textContent = modal.dataset.recordId ? 'Verify This Settlement' : 'Use This Expected Amount';
            })
            .catch(error => {
                console.error('Error fetching/processing meter readings for verification modal:', error);
                modalContent.innerHTML = `<p class="text-center text-red-600 py-4">Error: ${error.message}.</p>`;
                useReadingsBtn.disabled = true;
            });
    }

    function showMeterReadingsModal(pumpId, date, targetFormIndex, shift) { // targetFormIndex is for form block
        modal.dataset.targetFormIndex = targetFormIndex; // Use a different dataset property
        modal.dataset.recordId = ''; // Clear recordId as this is for the form
        modal.classList.remove('hidden');
        modalContent.innerHTML = '<p class="text-center text-gray-500 py-4">Loading meter readings...</p>';
        useReadingsBtn.disabled = true;
        const timestamp = new Date().getTime();
        // Shift is already lowercase here
        fetch(`get_meter_readings.php?pump_id=${pumpId}&date=${date}&shift=${shift}&_=${timestamp}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                 if (!data.readings || !data.hasOwnProperty('total_expected_amount') || !data.hasOwnProperty('primary_fuel_price')) {
                    console.error("Incomplete data from get_meter_readings.php:", data);
                    throw new Error('Incomplete meter reading data from server.');
                }
                const localCurrencySymbol = data.currency_symbol || currencySymbol;
                let html = '<table class="min-w-full divide-y divide-gray-200 mb-4">';
                html += '<thead class="bg-gray-50"><tr>';
                ['Nozzle', 'Fuel'].forEach(h => html += `<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">${h}</th>`);
                ['Opening', 'Closing', 'Volume (L)', 'Price', 'Amount'].forEach(h => html += `<th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">${h}</th>`);
                html += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

                if (!data.readings || data.readings.length === 0) {
                    html += '<tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">No meter readings recorded.</td></tr>';
                } else {
                    data.readings.forEach(reading => {
                        const expectedAmount = parseFloat(reading.expected_amount) || 0;
                        const unitPrice = parseFloat(reading.unit_price) || 0;
                        const volume = parseFloat(reading.volume_dispensed) || 0;
                        const opening = parseFloat(reading.opening_reading) || 0;
                        const closing = parseFloat(reading.closing_reading) || 0;
                         html += `<tr>
                                    <td class="px-3 py-2 whitespace-nowrap">${reading.nozzle_number || 'N/A'}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">${reading.fuel_name || 'N/A'}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${opening.toFixed(3)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${closing.toFixed(3)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${volume.toFixed(3)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${unitPrice.toFixed(2)}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">${expectedAmount.toFixed(2)}</td>
                                 </tr>`;
                    });
                }
                html += '</tbody></table>';
                const totalExpected = parseFloat(data.total_expected_amount) || 0;
                const fuelPriceForTest = parseFloat(data.primary_fuel_price) || 0;
                html += `<div class="text-right font-semibold text-base mt-2">Total Expected Amount: ${localCurrencySymbol} ${totalExpected.toFixed(2)}</div>`;
                 if (fuelPriceForTest > 0) {
                    html += `<div class="text-right text-sm text-gray-600">(Fuel Price for Test Calc: ${localCurrencySymbol} ${fuelPriceForTest.toFixed(2)}/L)</div>`;
                } else {
                    html += `<div class="text-right text-sm text-red-600">(Warning: Fuel price for test calc not available!)</div>`;
                }
                modalContent.innerHTML = html;
                modalContent.dataset.totalExpected = totalExpected.toFixed(2);
                modalContent.dataset.fuelPrice = fuelPriceForTest.toFixed(2);
                useReadingsBtn.disabled = false;
                useReadingsBtn.textContent = 'Use This Expected Amount';
            })
            .catch(error => {
                console.error('Error fetching/processing meter readings for form modal:', error);
                modalContent.innerHTML = `<p class="text-center text-red-600 py-4">Error: ${error.message}.</p>`;
                useReadingsBtn.disabled = true;
            });
    }

    useReadingsBtn.addEventListener('click', function() {
        const totalExpected = modalContent.dataset.totalExpected;
        const fuelPrice = modalContent.dataset.fuelPrice;
        const recordId = modal.dataset.recordId; // For table verification
        const targetRowIndex = modal.dataset.targetRowIndex; // For table row UI update
        const targetFormIndex = modal.dataset.targetFormIndex; // For form input update

        if (totalExpected && fuelPrice) {
            if (recordId) { // Verifying an existing record from the table
                fetch('verify_settlement.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        record_id: recordId,
                        meter_expected: totalExpected, //This should map to 'expected_amount' in your DB table
                        fuel_price: fuelPrice
                        // Your verify_settlement.php should also update the 'status' to 'verified'
                        // and potentially 'adjusted_expected_amount' and 'difference'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateVerificationStatus(targetRowIndex, true); // Pass targetRowIndex
                        alert('Settlement verified successfully!');
                        // Refresh the page to update counts
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        throw new Error(data.error || 'Failed to verify settlement');
                    }
                })
                .catch(error => {
                    console.error('Error verifying settlement:', error);
                    alert('Error verifying settlement: ' + error.message);
                });
            } else if (targetFormIndex !== undefined && targetFormIndex !== null) { // Populating the form
                const meterExpectedInput = document.getElementById(`meter-expected-amount-${targetFormIndex}`);
                const fuelPriceInput = document.getElementById(`fuel-price-${targetFormIndex}`);
                if (meterExpectedInput && fuelPriceInput) {
                    meterExpectedInput.value = totalExpected;
                    fuelPriceInput.value = fuelPrice;
                    const pumpShiftBlock = document.getElementById(`pump-shift-block-${targetFormIndex}`);
                    if (pumpShiftBlock) {
                        pumpShiftBlock.classList.add('verified-pump');
                        const meterBtn = pumpShiftBlock.querySelector('.meter-readings-btn');
                        if (meterBtn) {
                            meterBtn.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Meter Reading Verified';
                            meterBtn.classList.add('text-green-600');
                            meterBtn.classList.remove('text-blue-600');
                        }
                    }
                    updateSettlementCalculation(targetFormIndex);
                } else {
                    console.error("Target input fields not found for form index:", targetFormIndex);
                }
            }
        } else {
            console.error("Missing totalExpected or fuelPrice from modal dataset.");
            alert("Could not use readings. Required data is missing.");
        }
        modal.classList.add('hidden');
        // Clear dataset attributes for next use
        delete modal.dataset.targetRowIndex;
        delete modal.dataset.targetFormIndex;
        delete modal.dataset.recordId;

    });

    document.getElementById('staff_id').addEventListener('change', function() {
        const staffId = this.value;
        const pumpShiftContainer = document.getElementById('pump-shift-container');
        const addMoreContainer = document.getElementById('add-more-container');
        pumpShiftContainer.innerHTML = '<p class="text-sm text-gray-500">Select a staff member to see their assigned pumps and shifts.</p>';
        currentPumpShiftIndex = 0; // Reset index

        if (!staffId) {
            addMoreContainer.classList.add('hidden');
            return;
        }
        const assignmentsForStaff = staffAssignments[staffId] || [];
        if (assignmentsForStaff.length === 0) {
            pumpShiftContainer.innerHTML = '<p class="text-sm text-red-500">No assignments found for this staff member on the selected date.</p>';
            addMoreContainer.classList.add('hidden');
            return;
        }
        addPumpShiftSelection(assignmentsForStaff); // Add first block
        const currentBlocks = pumpShiftContainer.querySelectorAll('[id^="pump-shift-block-"]').length;
        if (assignmentsForStaff.length > currentBlocks) {
            addMoreContainer.classList.remove('hidden');
        } else {
            addMoreContainer.classList.add('hidden');
        }
    });

    document.getElementById('add-more-btn').addEventListener('click', function() {
        const staffId = document.getElementById('staff_id').value;
        if (!staffId) return;
        const assignmentsForStaff = staffAssignments[staffId] || [];
        const pumpShiftContainer = document.getElementById('pump-shift-container');
        const currentBlocksCount = pumpShiftContainer.querySelectorAll('[id^="pump-shift-block-"]').length;

        if (assignmentsForStaff.length > currentBlocksCount) {
            addPumpShiftSelection(assignmentsForStaff);
        }
        // Re-check after adding
        if (assignmentsForStaff.length <= (currentBlocksCount + 1)) {
            document.getElementById('add-more-container').classList.add('hidden');
        }
    });

    window.addEventListener('load', () => {
        const staffId = document.getElementById('staff_id').value;
        if (staffId && staffAssignments[staffId]) { // Ensure staffAssignments[staffId] exists
            const assignmentsForStaff = staffAssignments[staffId];
            const pumpShiftContainer = document.getElementById('pump-shift-container');
            const currentBlocks = pumpShiftContainer.querySelectorAll('[id^="pump-shift-block-"]').length;
            if (currentBlocks > 0 && assignmentsForStaff.length > currentBlocks) { // Only show if blocks are already there and more can be added
                 document.getElementById('add-more-container').classList.remove('hidden');
            } else if (currentBlocks === 0 && assignmentsForStaff.length > 1) { // If no blocks yet, but more than 1 assignment total
                 // This case is handled by the staff_id change event which adds the first block
                 // and then checks if add-more should be visible.
            } else {
                document.getElementById('add-more-container').classList.add('hidden');
            }
        } else {
            document.getElementById('add-more-container').classList.add('hidden');
        }
    });

    function loadStaff(staffId) {
        const staffSelect = document.getElementById('staff_id');
        staffSelect.value = staffId;
        const event = new Event('change');
        staffSelect.dispatchEvent(event);
        document.getElementById('settlement-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
ob_end_flush();
?>