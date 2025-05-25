<?php
/**
 * Meter Reading Page
 * 
 * This page allows users to record fuel meter readings for pumps with improved date selection.
 */

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
$display_date = date('F d, Y', strtotime($reading_date));

// Get all pump nozzles with related information
$nozzles = getPumpNozzles();

// Get meter readings for the selected date if any exist
$existing_readings = getMeterReadingsByDate($reading_date);

// Create a lookup array for existing readings by nozzle ID
$existing_readings_by_nozzle = [];
foreach ($existing_readings as $reading) {
    $existing_readings_by_nozzle[$reading['nozzle_id']] = $reading;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate reading date
    if (empty($_POST['reading_date'])) {
        $errors['reading_date'] = 'Reading date is required';
    }
    
    // Validate readings
    if (isset($_POST['nozzle_id']) && is_array($_POST['nozzle_id'])) {
        foreach ($_POST['nozzle_id'] as $key => $nozzle_id) {
            // Validate opening reading
            if (!isset($_POST['opening_reading'][$key]) || $_POST['opening_reading'][$key] === '') {
                $errors["opening_reading_{$key}"] = 'Opening reading is required';
            } elseif (!is_numeric($_POST['opening_reading'][$key])) {
                $errors["opening_reading_{$key}"] = 'Opening reading must be a number';
            }
            
            // Validate closing reading
            if (!isset($_POST['closing_reading'][$key]) || $_POST['closing_reading'][$key] === '') {
                $errors["closing_reading_{$key}"] = 'Closing reading is required';
            } elseif (!is_numeric($_POST['closing_reading'][$key])) {
                $errors["closing_reading_{$key}"] = 'Closing reading must be a number';
            }
            
            // Validate closing > opening
            if (isset($_POST['opening_reading'][$key]) && isset($_POST['closing_reading'][$key]) &&
                is_numeric($_POST['opening_reading'][$key]) && is_numeric($_POST['closing_reading'][$key])) {
                if ($_POST['closing_reading'][$key] < $_POST['opening_reading'][$key]) {
                    $errors["closing_reading_{$key}"] = 'Closing reading must be greater than or equal to opening reading';
                }
            }
        }
    } else {
        $errors['general'] = 'No meter readings submitted';
    }
    
    // If no errors, proceed with saving the readings
    if (empty($errors)) {
        $reading_date = $_POST['reading_date'];
        $readings_saved = 0;
        
        foreach ($_POST['nozzle_id'] as $key => $nozzle_id) {
            $reading_data = [
                'nozzle_id' => (int)$nozzle_id,
                'reading_date' => $reading_date,
                'opening_reading' => (float)$_POST['opening_reading'][$key],
                'closing_reading' => (float)$_POST['closing_reading'][$key],
                'recorded_by' => $_SESSION['user_id'],
                'notes' => isset($_POST['notes'][$key]) ? trim($_POST['notes'][$key]) : ''
            ];
            
            if (addMeterReading($reading_data)) {
                $readings_saved++;
            }
        }
        
        if ($readings_saved > 0) {
            $success_message = "{$readings_saved} meter reading(s) saved successfully!";
            // Refresh page to show updated readings
            echo '<meta http-equiv="refresh" content="2;url=meter_reading.php?date=' . $reading_date . '">';
        } else {
            $errors['general'] = "Failed to save meter readings. Please try again.";
        }
    }
}

// Get today's date for min attribute
$today = date('Y-m-d');
?>

<!-- Main content -->
<div class="container mx-auto px-4 py-6">
    <?php if (!empty($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>

    <!-- Improved Date Selection Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-calendar-alt mr-2"></i> Select Date for Meter Readings
            </h2>
        </div>
        
        <div class="p-6">
            <form method="GET" action="" class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
                <div class="w-full md:w-auto">
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Reading Date</label>
                    <input type="date" id="date" name="date" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo $reading_date; ?>" max="<?php echo $today; ?>">
                </div>
                
                <div class="w-full md:w-auto self-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <i class="fas fa-search mr-2"></i> View Readings
                    </button>
                </div>
                
                <!-- Quick Date Navigation -->
                <div class="w-full md:flex-grow self-end flex justify-end space-x-2">
                    <a href="?date=<?php echo date('Y-m-d'); ?>" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm font-medium text-gray-700">
                        Today
                    </a>
                    <a href="?date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($reading_date))); ?>" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm font-medium text-gray-700">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="?date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($reading_date))); ?>" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm font-medium text-gray-700">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </form>
            
            <!-- Additional date information -->
            <div class="mt-4 text-sm text-gray-600">
                <p>
                    <i class="far fa-calendar-check text-blue-500 mr-1"></i> 
                    <strong>Selected Date:</strong> <?php echo $display_date; ?> 
                    <?php if ($reading_date == date('Y-m-d')): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2">Today</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($existing_readings)): ?>
                    <p class="mt-1 text-green-600">
                        <i class="fas fa-check-circle mr-1"></i> Readings have been recorded for this date.
                    </p>
                <?php else: ?>
                    <p class="mt-1 text-yellow-600">
                        <i class="fas fa-exclamation-circle mr-1"></i> No readings have been recorded for this date yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Readings Form Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-tachometer-alt mr-2"></i> Meter Readings for <?php echo $display_date; ?>
            </h2>
        </div>
        
        <form method="POST" action="" id="readingsForm" class="p-6">
            <input type="hidden" name="reading_date" value="<?php echo $reading_date; ?>">
            
            <?php if (empty($nozzles)): ?>
                <div class="text-center text-gray-500 py-8">
                    <p>No pump nozzles found. Please add pumps and nozzles first.</p>
                    <a href="add_pump.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-2"></i> Add New Pump
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 table-fixed">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">Opening Reading</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">Closing Reading</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($nozzles as $index => $nozzle): ?>
                                <?php 
                                $has_reading = isset($existing_readings_by_nozzle[$nozzle['nozzle_id']]);
                                $readonly = $has_reading ? 'readonly' : '';
                                $row_class = $has_reading ? 'bg-gray-50' : '';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($nozzle['pump_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        Nozzle #<?php echo htmlspecialchars($nozzle['nozzle_number']); ?>
                                        <input type="hidden" name="nozzle_id[]" value="<?php echo $nozzle['nozzle_id']; ?>">
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
                                    <td class="px-6 py-4 whitespace-nowrap min-w-[150px]">
                                        <input type="number" name="opening_reading[]" step="0.01" min="0" 
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo $has_reading ? 'bg-gray-100' : ''; ?>"
                                               value="<?php echo $has_reading ? number_format($existing_readings_by_nozzle[$nozzle['nozzle_id']]['opening_reading'], 2, '.', '') : ''; ?>"
                                               <?php echo $readonly; ?> required>
                                        <?php if (!empty($errors["opening_reading_{$index}"])): ?>
                                            <p class="mt-1 text-sm text-red-600"><?php echo $errors["opening_reading_{$index}"]; ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap min-w-[150px]">
                                        <input type="number" name="closing_reading[]" step="0.01" min="0" 
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo $has_reading ? 'bg-gray-100' : ''; ?>"
                                               value="<?php echo $has_reading ? number_format($existing_readings_by_nozzle[$nozzle['nozzle_id']]['closing_reading'], 2, '.', '') : ''; ?>"
                                               <?php echo $readonly; ?> required>
                                        <?php if (!empty($errors["closing_reading_{$index}"])): ?>
                                            <p class="mt-1 text-sm text-red-600"><?php echo $errors["closing_reading_{$index}"]; ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php if ($has_reading): ?>
                                            <span class="font-bold"><?php echo number_format($existing_readings_by_nozzle[$nozzle['nozzle_id']]['volume_dispensed'], 2); ?> L</span>
                                        <?php else: ?>
                                            <span class="text-gray-500" id="volume-<?php echo $index; ?>">Calculated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="text" name="notes[]" 
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 <?php echo $has_reading ? 'bg-gray-100' : ''; ?>"
                                               value="<?php echo $has_reading ? htmlspecialchars($existing_readings_by_nozzle[$nozzle['nozzle_id']]['notes']) : ''; ?>"
                                               <?php echo $readonly; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary section for existing readings -->
                <?php if (!empty($existing_readings)): ?>
                <div class="mt-6 p-5 bg-blue-50 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-800 mb-2">Reading Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php
                        // Calculate totals
                        $total_volume = 0;
                        $fuel_type_totals = [];
                        
                        foreach ($existing_readings as $reading) {
                            $total_volume += $reading['volume_dispensed'];
                            
                            $fuel_type = $reading['fuel_name'] ?? 'Unknown';
                            if (!isset($fuel_type_totals[$fuel_type])) {
                                $fuel_type_totals[$fuel_type] = 0;
                            }
                            $fuel_type_totals[$fuel_type] += $reading['volume_dispensed'];
                        }
                        ?>
                        
                        <div class="bg-white p-4 rounded-lg shadow-sm">
                            <p class="text-sm text-gray-500">Total Volume Dispensed</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($total_volume, 2); ?> L</p>
                        </div>
                        
                        <?php foreach ($fuel_type_totals as $fuel_type => $volume): ?>
                        <div class="bg-white p-4 rounded-lg shadow-sm">
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($fuel_type); ?></p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($volume, 2); ?> L</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-6 p-4 bg-gray-50 border-l-4 border-gray-500 text-gray-700">
                    <p class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        <span>Readings for this date have already been recorded. To make changes, please contact your administrator.</span>
                    </p>
                </div>
                <?php else: ?>
                    <!-- Submit Button -->
                    <div class="mt-6 flex justify-end">
                        <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                            <i class="fas fa-save mr-2"></i> Save Readings
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add input event listeners to calculate volume dispensed
        const form = document.getElementById('readingsForm');
        
        if (form) {
            const openingReadings = form.querySelectorAll('input[name="opening_reading[]"]');
            const closingReadings = form.querySelectorAll('input[name="closing_reading[]"]');
            
            for (let i = 0; i < openingReadings.length; i++) {
                if (!openingReadings[i].readOnly && !closingReadings[i].readOnly) {
                    const calculateVolume = function() {
                        const opening = parseFloat(openingReadings[i].value) || 0;
                        const closing = parseFloat(closingReadings[i].value) || 0;
                        const volumeElement = document.getElementById('volume-' + i);
                        
                        if (closing >= opening && volumeElement) {
                            const volume = (closing - opening).toFixed(4);
                            volumeElement.textContent = volume + ' L';
                            volumeElement.classList.add('font-bold', 'text-blue-600');
                            volumeElement.classList.remove('text-gray-500');
                        } else if (volumeElement) {
                            volumeElement.textContent = 'Error: Invalid';
                            volumeElement.classList.add('text-red-500', 'font-bold');
                            volumeElement.classList.remove('text-gray-500');
                        }
                    };
                    
                    openingReadings[i].addEventListener('input', calculateVolume);
                    closingReadings[i].addEventListener('input', calculateVolume);
                }
            }
        }
        
        // Highlight the selected date
        const datePicker = document.getElementById('date');
        if (datePicker) {
            datePicker.addEventListener('change', function() {
                // Auto-submit the form when date changes
                this.form.submit();
            });
        }
    });
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>