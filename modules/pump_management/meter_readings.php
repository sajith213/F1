<?php
/**
 * Meter Reading Page
 * 
 * This page allows users to record fuel meter readings for pumps.
 * Modified to exclude inactive pumps but show pumps without staff assignments.
 * Modified to display readings and volumes to 4 decimal places.
 * Improved save handling to prevent errors and display issues.
 * Fixed NULL value handling with number_format() to prevent deprecation notices.
 * 
 * Note: Make sure your database schema has been updated to support 4 decimal places:
 * ALTER TABLE meter_readings 
 * MODIFY opening_reading DECIMAL(15,4),
 * MODIFY closing_reading DECIMAL(15,4),
 * MODIFY volume_dispensed DECIMAL(15,4);
 */
// Add this at the top of your meter_readings.php file
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Set page title
$page_title = "Record Meter Readings";
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / <a href="index.php" class="text-blue-600 hover:underline">Pump Management</a> / Record Meter Readings';

// Include header
include_once '../../includes/header.php';

// Include database connection
include_once '../../includes/db.php';

// Include the pump management functions
include_once 'functions.php';

// Check if user is authorized to access this module
if (!hasPermission('pump_management', $_SESSION['role'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Initialize variables
$errors = [];
$success_message = '';
$reading_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$is_backdated = strtotime($reading_date) < strtotime(date('Y-m-d'));
$display_date = date('F d, Y', strtotime($reading_date));

// Get all active pump nozzles with related information
$nozzles = getPumpNozzles();
$previous_day_readings = getPreviousDayClosingReadings($reading_date);
// Get meter readings for the selected date if any exist
$existing_readings = getMeterReadingsByDate($reading_date);

// Create a lookup array for existing readings by nozzle ID
$existing_readings_by_nozzle = [];
foreach ($existing_readings as $reading) {
    $existing_readings_by_nozzle[$reading['nozzle_id']] = $reading;
}

// Get staff assignments for the selected date
$staff_assignments_query = "SELECT sa.*, s.first_name, s.last_name, p.pump_id, p.pump_name 
                            FROM staff_assignments sa
                            JOIN staff s ON sa.staff_id = s.staff_id
                            JOIN pumps p ON sa.pump_id = p.pump_id
                            WHERE sa.assignment_date = ?";

$stmt = $conn->prepare($staff_assignments_query);
$stmt->bind_param("s", $reading_date);
$stmt->execute();
$assignments_result = $stmt->get_result();
$staff_assignments = [];

while ($assignment = $assignments_result->fetch_assoc()) {
    $staff_assignments[$assignment['pump_id']] = $assignment;
}
$stmt->close();

// Handle form submission for saving all readings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
    // Validate reading date
    if (empty($_POST['reading_date'])) {
        $errors['reading_date'] = 'Reading date is required';
    }
    
    // Validate readings
    $has_validation_errors = false;
    
    if (isset($_POST['nozzle_id']) && is_array($_POST['nozzle_id'])) {
        foreach ($_POST['nozzle_id'] as $key => $nozzle_id) {
            // Only validate if reading doesn't already exist for this nozzle on this date
            if (!isset($existing_readings_by_nozzle[$nozzle_id])) {
                // Validate opening reading
                if (!isset($_POST['opening_reading'][$key]) || $_POST['opening_reading'][$key] === '') {
                    $errors["opening_reading_{$key}"] = 'Opening reading is required';
                    $has_validation_errors = true;
                } elseif (!is_numeric($_POST['opening_reading'][$key])) {
                    $errors["opening_reading_{$key}"] = 'Opening reading must be a number';
                    $has_validation_errors = true;
                }
                
                // Validate closing reading
                if (!isset($_POST['closing_reading'][$key]) || $_POST['closing_reading'][$key] === '') {
                    $errors["closing_reading_{$key}"] = 'Closing reading is required';
                    $has_validation_errors = true;
                } elseif (!is_numeric($_POST['closing_reading'][$key])) {
                    $errors["closing_reading_{$key}"] = 'Closing reading must be a number';
                    $has_validation_errors = true;
                }
                
                // Validate closing > opening
                if (isset($_POST['opening_reading'][$key]) && isset($_POST['closing_reading'][$key]) &&
                    is_numeric($_POST['opening_reading'][$key]) && is_numeric($_POST['closing_reading'][$key])) {
                    if ((float)$_POST['closing_reading'][$key] < (float)$_POST['opening_reading'][$key]) {
                        $errors["closing_reading_{$key}"] = 'Closing reading must be greater than or equal to opening reading';
                        $has_validation_errors = true;
                    }
                }
            }
        }
    } else {
        $errors['general'] = 'No meter readings submitted';
        $has_validation_errors = true;
    }
    
    // Check if backdating reason is required
    if (isset($_POST['is_backdated']) && $_POST['is_backdated'] === '1' && empty($_POST['backdating_reason'])) {
        $errors['backdating_reason'] = 'Please provide a reason for backdating';
        $has_validation_errors = true;
    }
    
    // If no errors, proceed with saving the readings
    if (!$has_validation_errors) {
        $reading_date = $_POST['reading_date'];
        $readings_saved = 0;
        $backdating_reason = isset($_POST['backdating_reason']) ? trim($_POST['backdating_reason']) : '';
        
        foreach ($_POST['nozzle_id'] as $key => $nozzle_id) {
            // Only save if reading doesn't exist or if inputs are provided (for potential edits - though bulk edit isn't implemented here)
            if (!isset($existing_readings_by_nozzle[$nozzle_id]) && isset($_POST['opening_reading'][$key]) && isset($_POST['closing_reading'][$key])) {
                 $reading_data = [
                    'nozzle_id' => (int)$nozzle_id,
                    'reading_date' => $reading_date,
                    'opening_reading' => (float)$_POST['opening_reading'][$key],
                    'closing_reading' => (float)$_POST['closing_reading'][$key],
                    'recorded_by' => $_SESSION['user_id'],
                    'notes' => isset($_POST['notes'][$key]) ? trim($_POST['notes'][$key]) : ''
                ];
                
                // Add backdating reason to notes if provided
                if (!empty($backdating_reason)) {
                    $reading_data['notes'] = "BACKDATED: {$backdating_reason} | " . $reading_data['notes'];
                }
                
                if (addMeterReading($reading_data)) {
                    $readings_saved++;
                }
            }
        }
        
        if ($readings_saved > 0) {
            $success_message = "{$readings_saved} new meter reading(s) saved successfully!";
            // Instead of meta refresh, use JavaScript
            echo '<script>
                setTimeout(function() {
                    window.location.href = "meter_readings.php?date=' . $reading_date . '&saved=1";
                }, 2000);
            </script>';
        } elseif (empty($errors)) {
             // If no new readings were saved but there were no errors (e.g., all were already submitted)
             $success_message = "No new readings to save.";
        } else {
            $errors['general'] = "Failed to save meter readings. Please check errors below and try again.";
        }
    } else {
        // If there are validation errors, add a general error message
        if (!isset($errors['general'])) {
            $errors['general'] = "Validation failed. Please check the errors below and try again.";
        }
    }
}

// Handle form submission for saving individual readings (for new or edited)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_individual'])) {
    $nozzle_id = (int)$_POST['nozzle_id'];
    $opening_reading = $_POST['opening_reading'];
    $closing_reading = $_POST['closing_reading'];
    $notes = $_POST['notes'] ?? '';
    $is_backdated = isset($_POST['is_backdated']) && $_POST['is_backdated'] === '1';
    $backdating_reason = isset($_POST['backdating_reason']) ? trim($_POST['backdating_reason']) : '';
    
    // Validate readings
    $has_errors = false;
    
    if ($opening_reading === '' || !is_numeric($opening_reading)) {
        $errors["opening_reading_{$nozzle_id}"] = 'Opening reading must be a valid number';
        $has_errors = true;
    }
    
    if ($closing_reading === '' || !is_numeric($closing_reading)) {
        $errors["closing_reading_{$nozzle_id}"] = 'Closing reading must be a valid number';
        $has_errors = true;
    }
    
    if (is_numeric($opening_reading) && is_numeric($closing_reading)) {
        if ((float)$closing_reading < (float)$opening_reading) {
            $errors["closing_reading_{$nozzle_id}"] = 'Closing reading must be >= opening reading';
            $has_errors = true;
        }
    }
    
    // Validate backdating reason if applicable (applies to both new backdated and edited backdated)
    if ($is_backdated && empty($backdating_reason)) {
         // Check if it's an existing reading being edited; if so, backdating reason might already be in notes
         $is_existing = isset($existing_readings_by_nozzle[$nozzle_id]);
         $existing_notes = $is_existing ? $existing_readings_by_nozzle[$nozzle_id]['notes'] : '';
         
         // Only require reason if it's truly a new backdated entry or editing without a previous reason
         if (!$is_existing || !str_contains($existing_notes, 'BACKDATED:')) {
              $errors["backdating_reason_{$nozzle_id}"] = 'Please provide a reason for backdating';
              $has_errors = true;
         }
    }
    
    if (!$has_errors) {
        $reading_data = [
            'nozzle_id' => $nozzle_id,
            'reading_date' => $reading_date,
            'opening_reading' => (float)$opening_reading,
            'closing_reading' => (float)$closing_reading,
            'recorded_by' => $_SESSION['user_id'], // Always update recorded_by on edit/save
            'notes' => trim($notes)
        ];

        // Check if it's an edit of an existing record
        $is_existing = isset($existing_readings_by_nozzle[$nozzle_id]);
        $existing_notes = $is_existing ? $existing_readings_by_nozzle[$nozzle_id]['notes'] : '';
        
        // Handle backdating note: Add if new backdated, preserve if editing existing backdated
        if ($is_backdated) {
            if (!empty($backdating_reason) && !str_contains($existing_notes, 'BACKDATED:')) {
                // Add new backdating reason if provided and not already present
                 $reading_data['notes'] = "BACKDATED: {$backdating_reason} | " . $reading_data['notes'];
            } elseif(str_contains($existing_notes, 'BACKDATED:')) {
                 // If editing an existing backdated record, ensure the original reason isn't lost
                 // Extract original reason + original notes part
                 $parts = explode('|', $existing_notes, 2);
                 $original_backdate_note = trim($parts[0]);
                 // Use the newly submitted notes but prepend the original backdate reason part
                 $reading_data['notes'] = $original_backdate_note . " | " . trim($notes);
            }
        } else {
             // If *not* backdating now, but it *was* backdated, remove the backdating prefix (unlikely scenario but handles edge case)
             if (str_contains($existing_notes, 'BACKDATED:')) {
                 $parts = explode('|', $existing_notes, 2);
                 $reading_data['notes'] = isset($parts[1]) ? trim($parts[1]) : trim($notes); // Keep only the notes part after '|' or the new notes
             }
        }
        
        // Determine if adding or updating
        if ($is_existing) {
             $reading_data['reading_id'] = $existing_readings_by_nozzle[$nozzle_id]['reading_id'];
             if (updateMeterReading($reading_data)) {
                  $success_message = "Meter reading for nozzle #{$nozzle_id} updated successfully!";
                  echo '<script>
                      setTimeout(function() {
                          window.location.href = "meter_readings.php?date=' . $reading_date . '&saved=' . $nozzle_id . '";
                      }, 1500);
                  </script>';
             } else {
                  $errors["reading_{$nozzle_id}"] = "Failed to update meter reading. Please try again.";
             }
        } else {
             if (addMeterReading($reading_data)) {
                  $success_message = "Meter reading for nozzle #{$nozzle_id} saved successfully!";
                  echo '<script>
                      setTimeout(function() {
                          window.location.href = "meter_readings.php?date=' . $reading_date . '&saved=' . $nozzle_id . '";
                      }, 1500);
                  </script>';
             } else {
                  $errors["reading_{$nozzle_id}"] = "Failed to save meter reading. Please try again.";
             }
        }
    } else {
         // To redisplay errors correctly near the specific row, pass them back.
         // Also add general error
         $errors['general'] = "Error saving reading for Nozzle #{$nozzle_id}. Please check the fields.";
    }
}
?>

<div class="container mx-auto px-4 py-6">
    <!-- Saving indicator overlay - will be shown during save operations -->
    <div id="savingOverlay" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl text-center max-w-md">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mx-auto mb-4"></div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Saving Changes</h3>
            <p class="text-gray-600">Please wait while your data is being saved...</p>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo htmlspecialchars($errors['general']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-calendar-alt mr-2"></i> Select Date
            </h2>
        </div>
        
        <div class="p-6">
            <form method="GET" action="" class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
                <div class="w-full md:w-auto">
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Reading Date</label>
                    <input type="date" id="date" name="date" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($reading_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                    <p class="mt-1 text-sm text-gray-500">You can select past dates to backdate readings.</p>
                </div>
                
                <div class="w-full md:w-auto self-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <i class="fas fa-search mr-2"></i> View Readings
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($is_backdated): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Backdating Notice:</strong> You are recording/viewing readings for a past date (<?php echo htmlspecialchars($display_date); ?>).
                    A reason is required when saving new or editing backdated entries.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-users mr-2"></i> Staff Assignments for <?php echo htmlspecialchars($display_date); ?>
            </h2>
        </div>
        
        <div class="p-6">
            <?php if (empty($staff_assignments)): ?>
                <div class="text-center text-gray-500 py-4">
                    <p>No staff assignments found for this date.</p>
                    <a href="../../modules/staff_management/assign_staff.php" class="mt-2 inline-block text-blue-600 hover:underline">
                        <i class="fas fa-user-plus mr-1"></i> Assign Staff
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($staff_assignments as $assignment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assignment['pump_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                                <?php echo strtoupper(substr($assignment['first_name'], 0, 1) . substr($assignment['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch ($assignment['shift']) {
                                                case 'morning': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'afternoon': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'evening': echo 'bg-indigo-100 text-indigo-800'; break;
                                                case 'night': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst(htmlspecialchars($assignment['shift'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch ($assignment['status']) {
                                                case 'assigned': echo 'bg-green-100 text-green-800'; break;
                                                case 'completed': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'absent': echo 'bg-red-100 text-red-800'; break;
                                                case 'reassigned': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst(htmlspecialchars($assignment['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-tachometer-alt mr-2"></i> Meter Readings for <?php echo htmlspecialchars($display_date); ?>
            </h2>
        </div>
        
        <div class="p-6">
            <?php if (empty($nozzles)): ?>
                <div class="text-center text-gray-500 py-8">
                    <p>No pump nozzles found. Please add pumps and nozzles first.</p>
                    <a href="add_pump.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-2"></i> Add New Pump
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="allReadingsForm">
                    <input type="hidden" name="reading_date" value="<?php echo htmlspecialchars($reading_date); ?>">
                    <input type="hidden" name="is_backdated" value="<?php echo $is_backdated ? '1' : '0'; ?>">
                    
                    <?php if ($is_backdated): ?>
                    <div class="mb-6">
                        <label for="backdating_reason" class="block text-sm font-medium text-gray-700">Reason for Backdating (Required for new entries) <span class="text-red-500">*</span></label>
                        <textarea id="backdating_reason" name="backdating_reason" rows="2" 
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo !empty($errors['backdating_reason']) ? 'border-red-500' : ''; ?>"
                                  placeholder="Please explain why you are recording readings for a past date (required if saving new backdated readings)"><?php echo isset($_POST['backdating_reason']) ? htmlspecialchars($_POST['backdating_reason']) : ''; ?></textarea>
                        <?php if (!empty($errors['backdating_reason'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['backdating_reason']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Staff</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 160px;">Opening Reading</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 160px;">Closing Reading</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 200px;">Notes</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($nozzles as $index => $nozzle): ?>
                                    <?php 
                                    $nozzle_id = $nozzle['nozzle_id'];
                                    $has_reading = isset($existing_readings_by_nozzle[$nozzle_id]);
                                    $existing_reading_data = $has_reading ? $existing_readings_by_nozzle[$nozzle_id] : null;
                                    $readonly = $has_reading ? 'readonly disabled' : '';
                                    
                                    // Check if this nozzle's pump has an assigned staff
                                    $assigned_staff = isset($staff_assignments[$nozzle['pump_id']]) ? 
                                        $staff_assignments[$nozzle['pump_id']] : null;
                                        
                                    // Highlight pumps without staff assignments
                                    $row_class = $has_reading ? 'bg-gray-50' : ''; // Default background for saved rows
                                    $row_class .= !$assigned_staff ? ' border-l-4 border-yellow-300' : ''; // Yellow border for unassigned
                                    
                                    // Highlight recently saved rows if coming back from a save
                                    if (isset($_GET['saved']) && ($_GET['saved'] == $nozzle_id || $_GET['saved'] == '1')) {
                                        $row_class .= ' transition-all duration-1000 bg-green-50'; // Add green highlight for saved rows
                                    }
                                    
                                    // Handle potential errors for this specific nozzle from individual save attempts
                                    $opening_error = isset($errors["opening_reading_{$nozzle_id}"]) ? $errors["opening_reading_{$nozzle_id}"] : 
                                                    (isset($errors["opening_reading_{$index}"]) ? $errors["opening_reading_{$index}"] : null);
                                    $closing_error = isset($errors["closing_reading_{$nozzle_id}"]) ? $errors["closing_reading_{$nozzle_id}"] : 
                                                    (isset($errors["closing_reading_{$index}"]) ? $errors["closing_reading_{$index}"] : null);
                                    $backdate_error = isset($errors["backdating_reason_{$nozzle_id}"]) ? $errors["backdating_reason_{$nozzle_id}"] : null;
                                    $general_nozzle_error = isset($errors["reading_{$nozzle_id}"]) ? $errors["reading_{$nozzle_id}"] : null;

                                    // Determine input background based on assignment and errors
                                    $input_bg_class = $has_reading ? 'bg-gray-100' : (!$assigned_staff ? 'bg-yellow-50' : '');
                                    $input_border_class = '';
                                    if ($opening_error || $closing_error || $backdate_error || $general_nozzle_error) {
                                        $input_border_class = 'border-red-500'; // Red border for errors
                                        $row_class .= ' bg-red-50'; // Highlight row with error
                                    }

                                    // Get note value, handling potential backdating prefix preservation
                                    $note_value = '';
                                    if ($has_reading) {
                                        $note_value = $existing_reading_data['notes'];
                                        // Remove backdating prefix for display in input if needed, handled during save logic
                                        if(str_contains($note_value, 'BACKDATED:')) {
                                             $parts = explode('|', $note_value, 2);
                                             $note_value = isset($parts[1]) ? trim($parts[1]) : '';
                                        }
                                    } elseif (!$assigned_staff && !$has_reading) {
                                         $note_value = 'No staff assigned to this pump';
                                    }
                                    $note_value = isset($_POST['notes'][$index]) ? htmlspecialchars($_POST['notes'][$index]) : htmlspecialchars($note_value); // Prioritize POST data on error/reload

                                    ?>
                                    <tr class="<?php echo $row_class; ?>" id="row-nozzle-<?php echo $nozzle_id; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($nozzle['pump_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Nozzle #<?php echo htmlspecialchars($nozzle['nozzle_number']); ?>
                                            <input type="hidden" name="nozzle_id[]" value="<?php echo $nozzle_id; ?>">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                <?php 
                                                switch ($nozzle['fuel_type_id']) {
                                                    case 1: echo 'bg-green-100 text-green-800'; break; // Petrol 92
                                                    case 2: echo 'bg-blue-100 text-blue-800'; break;   // Petrol 95
                                                    case 3: echo 'bg-red-100 text-red-800'; break;     // Diesel
                                                    case 4: echo 'bg-purple-100 text-purple-800'; break; // Super Diesel
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($nozzle['fuel_name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($assigned_staff): ?>
                                                <div class="flex items-center">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?php echo htmlspecialchars($assigned_staff['first_name'] . ' ' . $assigned_staff['last_name']); ?>
                                                        <span class="ml-1 text-xs text-blue-600">(<?php echo ucfirst(htmlspecialchars($assigned_staff['shift'])); ?> shift)</span>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-yellow-600">
                                                    <i class="fas fa-exclamation-circle"></i> No staff assigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="number" name="opening_reading[]" id="opening-<?php echo $nozzle_id; ?>" step="0.0001" min="0" 
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo $input_bg_class; ?> <?php echo $input_border_class; ?>"
                                                   value="<?php 
                                                        $opening_val = '';
                                                        if ($has_reading) {
                                                            $opening_val = '0.0000'; // Default initialization
                                                            if ($existing_reading_data['opening_reading'] !== null) {
                                                                $opening_val = number_format((float)$existing_reading_data['opening_reading'], 4, '.', '');
                                                            }
                                                        } elseif (isset($previous_day_readings[$nozzle_id])) {
                                                            $opening_val = '0.0000'; // Default initialization
                                                            if ($previous_day_readings[$nozzle_id] !== null) {
                                                                $opening_val = number_format((float)$previous_day_readings[$nozzle_id], 4, '.', '');
                                                            }
                                                        }
                                                        
                                                        // Prioritize POST data on error/reload if field wasn't saved yet
                                                        if (!$has_reading && isset($_POST['opening_reading'][$index])) {
                                                             $opening_val = htmlspecialchars($_POST['opening_reading'][$index]);
                                                        } elseif ($has_reading && isset($_POST['opening_reading']) && isset($_POST['nozzle_id']) && is_array($_POST['nozzle_id']) && isset($_POST['nozzle_id'][$index]) && $_POST['nozzle_id'][$index] == $nozzle_id) {
                                                             // Handle case where individual save failed and form reloaded
                                                             $opening_val = htmlspecialchars($_POST['opening_reading']);
                                                        }
                                                        echo $opening_val;
                                                   ?>"
                                                   <?php echo $readonly; ?> required>
                                            <?php if ($opening_error): ?>
                                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($opening_error); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="number" name="closing_reading[]" id="closing-<?php echo $nozzle_id; ?>" step="0.0001" min="0" 
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo $input_bg_class; ?> <?php echo $input_border_class; ?>"
                                                   value="<?php 
                                                        $closing_val = '';
                                                        if ($has_reading) {
                                                            $closing_val = '0.0000'; // Default initialization
                                                            if ($existing_reading_data['closing_reading'] !== null) {
                                                                $closing_val = number_format((float)$existing_reading_data['closing_reading'], 4, '.', '');
                                                            }
                                                        }
                                                        
                                                        // Prioritize POST data on error/reload if field wasn't saved yet
                                                        if (!$has_reading && isset($_POST['closing_reading'][$index])) {
                                                             $closing_val = htmlspecialchars($_POST['closing_reading'][$index]);
                                                        } elseif ($has_reading && isset($_POST['closing_reading']) && isset($_POST['nozzle_id']) && is_array($_POST['nozzle_id']) && isset($_POST['nozzle_id'][$index]) && $_POST['nozzle_id'][$index] == $nozzle_id) {
                                                             // Handle case where individual save failed and form reloaded
                                                             $closing_val = htmlspecialchars($_POST['closing_reading']);
                                                        }
                                                        echo $closing_val;
                                                   ?>"
                                                   <?php echo $readonly; ?> required>
                                            <?php if ($closing_error): ?>
                                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($closing_error); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
    <span id="volume-<?php echo $nozzle_id; ?>" data-nozzle-id="<?php echo $nozzle_id; ?>">
        <?php if ($has_reading): ?>
            <?php 
            if ($existing_reading_data['volume_dispensed'] !== null) {
                echo number_format((float)$existing_reading_data['volume_dispensed'], 4, '.', '');
            } else {
                echo '0.0000';
            }
            ?> L
        <?php else: ?>
            <span class="text-gray-500">Calculated</span>
        <?php endif; ?>
    </span>
</td>
                                         <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="text" name="notes[]" id="notes-<?php echo $nozzle_id; ?>"
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo $input_bg_class; ?> <?php echo $input_border_class; ?>"
                                                   value="<?php echo $note_value; ?>"
                                                   <?php echo $readonly; ?>>
                                             <?php if ($backdate_error): // Show backdate error near notes ?>
                                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($backdate_error); ?></p>
                                             <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?php if (!$has_reading): ?>
                                                <button type="button" onclick="saveIndividualReading(<?php echo $nozzle_id; ?>)" 
                                                        class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
                                                        id="save-btn-<?php echo $nozzle_id; ?>">
                                                    Save
                                                </button>
                                                 <?php if ($general_nozzle_error): ?>
                                                    <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($general_nozzle_error); ?></p>
                                                 <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-green-600 text-xs block" id="status-<?php echo $nozzle_id; ?>">
                                                    <i class="fas fa-check-circle"></i> Saved
                                                </span>
                                                <button type="button" onclick="editReading(<?php echo $nozzle_id; ?>, event)" 
                                                        class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 mt-1"
                                                        id="edit-btn-<?php echo $nozzle_id; ?>">
                                                    Edit
                                                </button>
                                                <?php if ($general_nozzle_error): // Show general error here too if editing failed ?>
                                                    <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($general_nozzle_error); ?></p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php 
                      // Check if there are any nozzles for which readings haven't been entered yet
                      $has_unsaved_readings = false;
                      foreach ($nozzles as $n) {
                          if (!isset($existing_readings_by_nozzle[$n['nozzle_id']])) {
                              $has_unsaved_readings = true;
                              break;
                          }
                      }
                    ?>
                    <?php if ($has_unsaved_readings): ?>
                        <div class="mt-6 flex justify-end">
                            <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                            <button type="submit" name="save_all" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center" 
                                    onclick="return validateAndSubmitAllReadings()">
                                <i class="fas fa-save mr-2"></i> Save All Unsaved Readings
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
                
                <form method="POST" action="" id="individualReadingForm" style="display: none;">
                    <input type="hidden" name="reading_date" value="<?php echo htmlspecialchars($reading_date); ?>">
                    <input type="hidden" name="nozzle_id" id="individual_nozzle_id">
                    <input type="hidden" name="opening_reading" id="individual_opening_reading">
                    <input type="hidden" name="closing_reading" id="individual_closing_reading">
                    <input type="hidden" name="notes" id="individual_notes">
                    <input type="hidden" name="is_backdated" value="<?php echo $is_backdated ? '1' : '0'; ?>" id="individual_is_backdated">
                    <input type="hidden" name="backdating_reason" id="individual_backdating_reason">
                    <input type="hidden" name="save_individual" value="1">
                </form>
                
                <?php 
                    $unassigned_pumps_exist = false;
                    $pump_ids_processed = [];
                    foreach ($nozzles as $n) {
                        if (!isset($staff_assignments[$n['pump_id']]) && !in_array($n['pump_id'], $pump_ids_processed)) {
                            $unassigned_pumps_exist = true;
                            $pump_ids_processed[] = $n['pump_id'];
                        }
                    }
                 ?>
                 <?php if ($unassigned_pumps_exist): ?>
                    <div class="mt-6 p-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700">
                        <p class="font-bold">Information</p>
                        <p>Pumps without staff assignments for <?php echo htmlspecialchars($display_date); ?> are highlighted with a yellow border. You can still record readings for these pumps if necessary.</p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($existing_readings)): ?>
                    <div class="mt-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700">
                        <p class="font-bold">Notice</p>
                        <p>Some readings for this date have already been recorded (grey background). You can edit these readings by clicking the "Edit" button.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight saved rows that came from GET parameter
        if (window.location.search.includes('saved=')) {
            // Remove highlighting after 3 seconds
            setTimeout(() => {
                document.querySelectorAll('.bg-green-50').forEach(row => {
                    row.classList.remove('bg-green-50');
                    row.classList.add('bg-gray-50');
                });
            }, 3000);
        }
        
        // Add input event listeners to calculate volume dispensed
        const allNozzles = <?php echo json_encode(array_column($nozzles, 'nozzle_id')); ?>;
        
        allNozzles.forEach(nozzleId => {
            const openingInput = document.getElementById(`opening-${nozzleId}`);
            const closingInput = document.getElementById(`closing-${nozzleId}`);
            const volumeSpan = document.getElementById(`volume-${nozzleId}`);
            
            if (openingInput && closingInput && volumeSpan) {
                const calculateVolume = function() {
                    // Only calculate if not already saved OR if editing
                    if (!openingInput.hasAttribute('readonly') || !closingInput.hasAttribute('readonly')) {
                        const opening = parseFloat(openingInput.value) || 0;
                        const closing = parseFloat(closingInput.value) || 0;
                        
                        if (openingInput.value !== '' && closingInput.value !== '') { // Check if both have values
                             if (closing >= opening) {
                                const volume = (closing - opening).toFixed(4); // Calculate to 4 decimal places
                                volumeSpan.textContent = volume;
                                volumeSpan.classList.remove('text-red-600', 'text-gray-500');
                                volumeSpan.classList.add('text-gray-900');
                                // Clear potential previous error message
                                const closingErrorP = closingInput.parentNode.querySelector('.text-red-600');
                                if (closingErrorP && closingErrorP.textContent.includes('must be greater')) {
                                     closingErrorP.remove();
                                     closingInput.classList.remove('border-red-500');
                                }
                                
                                // Set the validation state for this row
                                volumeSpan.dataset.valid = 'true';
                             } else {
                                volumeSpan.textContent = 'Error'; // Simpler error message
                                volumeSpan.classList.add('text-red-600');
                                volumeSpan.classList.remove('text-gray-900', 'text-gray-500');
                                // Optionally add error message below closing input if not already there
                                let closingErrorP = closingInput.parentNode.querySelector('.text-red-600');
                                if (!closingErrorP) {
                                     closingErrorP = document.createElement('p');
                                     closingErrorP.className = 'mt-1 text-sm text-red-600';
                                     closingInput.parentNode.appendChild(closingErrorP);
                                }
                                closingErrorP.textContent = 'Closing must be >= Opening';
                                closingInput.classList.add('border-red-500');
                                
                                // Set the validation state for this row
                                volumeSpan.dataset.valid = 'false';
                             }
                        } else {
                             // If one or both are empty, reset to 'Calculated' or existing value
                             const existingVolume = volumeSpan.dataset.existingVolume || '';
                             if (existingVolume) {
                                 volumeSpan.textContent = existingVolume;
                                 volumeSpan.dataset.valid = 'true';
                             } else if (!openingInput.hasAttribute('readonly')) { // Only show 'Calculated' if it's a new entry
                                 volumeSpan.textContent = 'Calculated';
                                 volumeSpan.classList.add('text-gray-500');
                                 volumeSpan.classList.remove('text-gray-900', 'text-red-600');
                                 // Need both values - consider invalid until both are provided
                                 volumeSpan.dataset.valid = 'pending';
                             }
                        }
                    }
                };
                
                // Add event listeners even if the input is readonly initially
                // They'll become active when the field is enabled for editing
                openingInput.addEventListener('input', calculateVolume);
                closingInput.addEventListener('input', calculateVolume);

                // Store existing volume if present, for resetting calculations
                if (volumeSpan.textContent.match(/^\d+\.\d+$/)) {
                    volumeSpan.dataset.existingVolume = volumeSpan.textContent;
                    volumeSpan.dataset.valid = 'true';
                }
                
                // Initial calculation on load for non-saved entries
                if (!openingInput.hasAttribute('readonly')) {
                     calculateVolume();
                }
            }
        });
    });
    
    // Function to validate all rows before submitting the form
    function validateAndSubmitAllReadings() {
        // Get all unsaved rows that need validation
        const allNozzles = <?php echo json_encode(array_column($nozzles, 'nozzle_id')); ?>;
        let hasErrors = false;
        let firstErrorNozzleId = null;
        
        // First clear all existing error messages
        document.querySelectorAll('.text-red-600').forEach(el => {
            // Don't remove server-side error messages
            if (!el.dataset.serverError) {
                el.remove();
            }
        });
        
        // Validate each row
        allNozzles.forEach(nozzleId => {
            const openingInput = document.getElementById(`opening-${nozzleId}`);
            const closingInput = document.getElementById(`closing-${nozzleId}`);
            const volumeSpan = document.getElementById(`volume-${nozzleId}`);
            
            // Only validate unsaved rows
            if (openingInput && !openingInput.hasAttribute('readonly')) {
                // Check if required fields are filled
                if (!openingInput.value.trim()) {
                    showError(openingInput, 'Opening reading is required');
                    hasErrors = true;
                    if (!firstErrorNozzleId) firstErrorNozzleId = nozzleId;
                } else if (!isNumeric(openingInput.value)) {
                    showError(openingInput, 'Opening reading must be a number');
                    hasErrors = true;
                    if (!firstErrorNozzleId) firstErrorNozzleId = nozzleId;
                }
                
                if (!closingInput.value.trim()) {
                    showError(closingInput, 'Closing reading is required');
                    hasErrors = true;
                    if (!firstErrorNozzleId) firstErrorNozzleId = nozzleId;
                } else if (!isNumeric(closingInput.value)) {
                    showError(closingInput, 'Closing reading must be a number');
                    hasErrors = true;
                    if (!firstErrorNozzleId) firstErrorNozzleId = nozzleId;
                }
                
                // Check if closing >= opening
                if (isNumeric(openingInput.value) && isNumeric(closingInput.value)) {
                    const opening = parseFloat(openingInput.value);
                    const closing = parseFloat(closingInput.value);
                    
                    if (closing < opening) {
                        showError(closingInput, 'Closing reading must be >= opening reading');
                        volumeSpan.textContent = 'Error';
                        volumeSpan.classList.add('text-red-600');
                        volumeSpan.classList.remove('text-gray-900', 'text-gray-500');
                        hasErrors = true;
                        if (!firstErrorNozzleId) firstErrorNozzleId = nozzleId;
                    }
                }
            }
        });
        
        // Check if backdating reason is required
        const isBackdated = document.querySelector('input[name="is_backdated"]').value === '1';
        const backdatingReasonInput = document.getElementById('backdating_reason');
        
        if (isBackdated && backdatingReasonInput && !backdatingReasonInput.value.trim()) {
            showError(backdatingReasonInput, 'Please provide a reason for backdating');
            hasErrors = true;
        }
        
        // If there are errors, scroll to the first error and don't submit
        if (hasErrors) {
            if (firstErrorNozzleId) {
                document.getElementById(`row-nozzle-${firstErrorNozzleId}`).scrollIntoView({
                    behavior: 'smooth', 
                    block: 'center'
                });
            }
            return false;
        }
        
        // Show saving overlay if validation passes
        showSavingOverlay();
        return true;
    }
    
    // Helper function to check if a value is numeric
    function isNumeric(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }
    
    // Show saving overlay during save operations
    function showSavingOverlay() {
        const overlay = document.getElementById('savingOverlay');
        if (overlay) {
            overlay.classList.remove('hidden');
        }
    }
    
    // Function to save individual reading (new or edited)
    function saveIndividualReading(nozzleId) {
        const openingInput = document.getElementById(`opening-${nozzleId}`);
        const closingInput = document.getElementById(`closing-${nozzleId}`);
        const notesInput = document.getElementById(`notes-${nozzleId}`);
        const volumeSpan = document.getElementById(`volume-${nozzleId}`);
        const isBackdated = document.getElementById('individual_is_backdated').value === '1';
        let backdatingReasonInput = document.getElementById('backdating_reason'); // Get the main reason textarea
        let backdatingReason = '';

        // --- Client-side Validation ---
        let isValid = true;
        
        // Clear previous errors visually
        document.querySelectorAll(`#row-nozzle-${nozzleId} .text-red-600`).forEach(el => el.remove());
        openingInput.classList.remove('border-red-500');
        closingInput.classList.remove('border-red-500');
        if (notesInput) notesInput.classList.remove('border-red-500'); // Check notesInput exists
        if (backdatingReasonInput) backdatingReasonInput.classList.remove('border-red-500');

        if (!openingInput.value || isNaN(parseFloat(openingInput.value))) {
            isValid = false;
            showError(openingInput, 'Opening reading is required and must be a number.');
        }
        
        if (!closingInput.value || isNaN(parseFloat(closingInput.value))) {
            isValid = false;
            showError(closingInput, 'Closing reading is required and must be a number.');
        }
        
        const opening = parseFloat(openingInput.value);
        const closing = parseFloat(closingInput.value);
        
        if (!isNaN(opening) && !isNaN(closing) && closing < opening) {
            isValid = false;
            showError(closingInput, 'Closing reading must be greater than or equal to opening reading.');
            
            // Update volume display to show error
            volumeSpan.textContent = 'Error';
            volumeSpan.classList.add('text-red-600');
            volumeSpan.classList.remove('text-gray-900', 'text-gray-500');
        }
        
        // Check if backdating reason is required
        if (isBackdated) {
            // Reason is required if it's a NEW backdated entry OR if editing and no reason was previously saved
            const isExisting = openingInput.hasAttribute('readonly'); // Check if it was initially readonly (means it existed)
            const existingNotes = notesInput.dataset.originalNotes || ''; // Use original notes if available
            
            if (!isExisting || !existingNotes.includes('BACKDATED:')) {
                 if (!backdatingReasonInput || !backdatingReasonInput.value.trim()) {
                      // Prompt if the main textarea is empty
                      backdatingReason = prompt(`BACKDATING: Please provide a reason for saving/editing this reading for ${document.getElementById('date').value}:`, '');
                      if (backdatingReason === null) { // User cancelled prompt
                          return; 
                      }
                      if (!backdatingReason.trim()) {
                           isValid = false;
                           // Show error near the main reason box if available, otherwise alert
                           if(backdatingReasonInput) {
                                showError(backdatingReasonInput, 'A reason for backdating is required.');
                           } else {
                                alert('A reason for backdating is required.');
                           }
                      }
                 } else {
                     // Use reason from the textarea if provided
                     backdatingReason = backdatingReasonInput.value.trim();
                 }
            } else {
                // If editing an existing backdated entry, the reason might be in the main textarea or just use existing
                 backdatingReason = backdatingReasonInput ? backdatingReasonInput.value.trim() : ''; // Allow updating reason via main text area
                 // No validation needed here as reason already exists conceptually
            }
        }
        
        if (!isValid) {
            alert('Please fix the errors before saving.');
            return;
        }
        // --- End Validation ---

        // Set values in the hidden form
        document.getElementById('individual_nozzle_id').value = nozzleId;
        document.getElementById('individual_opening_reading').value = openingInput.value; // Send the exact input value
        document.getElementById('individual_closing_reading').value = closingInput.value; // Send the exact input value
        document.getElementById('individual_notes').value = notesInput ? notesInput.value : ''; // Check notesInput exists
        document.getElementById('individual_backdating_reason').value = backdatingReason; // Send prompted/textarea reason
        
        // Show saving overlay
        showSavingOverlay();
        
        // Submit the form
        document.getElementById('individualReadingForm').submit();
    }
    
    // Function to enable editing of existing readings
    function editReading(nozzleId, event) {
        const openingInput = document.getElementById(`opening-${nozzleId}`);
        const closingInput = document.getElementById(`closing-${nozzleId}`);
        const notesInput = document.getElementById(`notes-${nozzleId}`);
        const row = document.getElementById(`row-nozzle-${nozzleId}`);
        const statusSpan = document.getElementById(`status-${nozzleId}`); // Get status span
        
        // Store original values in case user cancels
        if (openingInput && !openingInput.dataset.originalValue) {
            openingInput.dataset.originalValue = openingInput.value;
        }
        if (closingInput && !closingInput.dataset.originalValue) {
            closingInput.dataset.originalValue = closingInput.value;
        }
        // Store original notes in case user cancels or for backdating logic
        if (notesInput && !notesInput.dataset.originalNotes) {
             notesInput.dataset.originalNotes = notesInput.value;
        }

        // Enable fields
        openingInput.removeAttribute('readonly');
        openingInput.removeAttribute('disabled');
        closingInput.removeAttribute('readonly');
        closingInput.removeAttribute('disabled');
        if (notesInput) { // Check notesInput exists
             notesInput.removeAttribute('readonly');
             notesInput.removeAttribute('disabled');
             notesInput.classList.remove('bg-gray-100'); // Make background editable
        }
        openingInput.classList.remove('bg-gray-100');
        closingInput.classList.remove('bg-gray-100');
        
        // Highlight the row to indicate edit mode
        row.classList.remove('bg-gray-50'); // Remove saved background
        row.classList.add('bg-blue-50'); // Add editing background
        
        // Hide the 'Saved' status
        if (statusSpan) statusSpan.style.display = 'none'; 

        // Change the edit button to a save button
        const editButton = event.target;
        editButton.innerHTML = '<i class="fas fa-save"></i> Update'; // Change text/icon
        editButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        editButton.classList.add('bg-green-600', 'hover:bg-green-700');
        editButton.onclick = function() {
            saveIndividualReading(nozzleId); // Call the save function
        };
        
        // Add a Cancel button
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.innerHTML = 'Cancel';
        cancelButton.className = 'px-2 py-1 bg-gray-400 text-white text-xs rounded hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50 mt-1 ml-1';
        cancelButton.onclick = function() {
            // Revert changes using original stored values
            if (openingInput.dataset.originalValue) {
                openingInput.value = openingInput.dataset.originalValue;
            }
            if (closingInput.dataset.originalValue) {
                closingInput.value = closingInput.dataset.originalValue;
            }
            if (notesInput && notesInput.dataset.originalNotes) {
                notesInput.value = notesInput.dataset.originalNotes;
            }
            
            // Reset the UI without page reload
            openingInput.setAttribute('readonly', 'readonly');
            openingInput.setAttribute('disabled', 'disabled');
            closingInput.setAttribute('readonly', 'readonly');
            closingInput.setAttribute('disabled', 'disabled');
            if (notesInput) {
                notesInput.setAttribute('readonly', 'readonly');
                notesInput.setAttribute('disabled', 'disabled');
                notesInput.classList.add('bg-gray-100');
            }
            openingInput.classList.add('bg-gray-100');
            closingInput.classList.add('bg-gray-100');
            
            // Reset row style
            row.classList.remove('bg-blue-50');
            row.classList.add('bg-gray-50');
            
            // Show saved status
            if (statusSpan) statusSpan.style.display = 'block';
            
            // Reset button
            editButton.innerHTML = 'Edit';
            editButton.classList.remove('bg-green-600', 'hover:bg-green-700');
            editButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            editButton.onclick = function(e) {
                editReading(nozzleId, e);
            };
            
            // Remove cancel button
            this.remove();
        };
        editButton.parentNode.appendChild(cancelButton); // Add cancel next to update

        // Focus on the opening reading field
        openingInput.focus();
        
        // Trigger volume calculation in case values were edited
        openingInput.dispatchEvent(new Event('input')); 
    }

    // Helper function to show error messages below inputs
    function showError(inputElement, message) {
        // Remove existing error for this input first
        let existingError = inputElement.parentNode.querySelector('.text-red-600');
        if (existingError) existingError.remove();

        const errorP = document.createElement('p');
        errorP.className = 'mt-1 text-sm text-red-600';
        errorP.textContent = message;
        errorP.dataset.clientError = 'true'; // Mark as client-side error
        inputElement.parentNode.appendChild(errorP);
        inputElement.classList.add('border-red-500'); // Add red border to input
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>