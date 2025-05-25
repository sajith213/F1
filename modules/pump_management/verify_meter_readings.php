<?php
/**
 * Verify Meter Readings
 * 
 * Interface for admins and managers to verify pending meter readings
 */
ob_start();
// Set page title and load header
$page_title = "Verify Meter Readings";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Pump Management</a> / Verify Meter Readings";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check if user has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Process form submission for verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify'])) {
        $reading_id = (int)$_POST['reading_id'];
        // Fix: Don't pass $conn as the first parameter
        $result = verifyMeterReading($reading_id, $_SESSION['user_id']);
        
        if ($result) {
            $_SESSION['success_message'] = "Meter reading verified successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to verify meter reading.";
        }
        
        // Redirect to avoid resubmission
        header("Location: verify_meter_readings.php");
        exit;
    } elseif (isset($_POST['dispute'])) {
        $reading_id = (int)$_POST['reading_id'];
        $notes = $_POST['dispute_notes'] ?? '';
        // Fix: Don't pass $conn as the first parameter
        $result = disputeMeterReading($reading_id, $_SESSION['user_id'], $notes);
        
        if ($result) {
            $_SESSION['success_message'] = "Meter reading marked as disputed.";
        } else {
            $_SESSION['error_message'] = "Failed to dispute meter reading.";
        }
        
        // Redirect to avoid resubmission
        header("Location: verify_meter_readings.php");
        exit;
    } elseif (isset($_POST['bulk_verify'])) {
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($_POST['selected_readings'] as $reading_id) {
            // Fix: Don't pass $conn as the first parameter
            $result = verifyMeterReading((int)$reading_id, $_SESSION['user_id']);
            if ($result) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_message'] = "$success_count meter readings verified successfully.";
            if ($failed_count > 0) {
                $_SESSION['success_message'] .= " $failed_count readings failed to verify.";
            }
        } else {
            $_SESSION['error_message'] = "Failed to verify selected meter readings.";
        }
        
        // Redirect to avoid resubmission
        header("Location: verify_meter_readings.php");
        exit;
    }
}

// Get pending meter readings
$pending_readings = getPendingMeterReadings();

// Filter by date if provided
$filter_date = $_GET['date'] ?? null;
if ($filter_date) {
    $filtered_readings = [];
    foreach ($pending_readings as $reading) {
        if ($reading['reading_date'] === $filter_date) {
            $filtered_readings[] = $reading;
        }
    }
    $pending_readings = $filtered_readings;
}

// Get unique dates from pending readings for the filter dropdown
$unique_dates = [];
$all_pending_readings = getPendingMeterReadings();
foreach ($all_pending_readings as $reading) {
    if (!in_array($reading['reading_date'], $unique_dates)) {
        $unique_dates[] = $reading['reading_date'];
    }
}
rsort($unique_dates); // Sort dates in descending order

// Handle flash messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!-- Notification Messages -->
<?php if (!empty($success_message)): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
    <p><?= htmlspecialchars($success_message) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
    <p><?= htmlspecialchars($error_message) ?></p>
</div>
<?php endif; ?>

<!-- Date Filter -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form id="date-form" class="flex flex-wrap items-end gap-4">
        <div>
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Filter by Date</label>
            <select id="date" name="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    onchange="document.getElementById('date-form').submit()">
                <option value="">All Pending Readings</option>
                <?php foreach ($unique_dates as $date): ?>
                    <option value="<?= htmlspecialchars($date) ?>" <?= $filter_date === $date ? 'selected' : '' ?>>
                        <?= date('F d, Y', strtotime($date)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="ml-auto">
            <a href="index.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Back to Pump Management
            </a>
        </div>
    </form>
</div>

<!-- Pending Meter Readings -->
<div class="bg-white rounded-lg shadow">
    <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-800">Pending Meter Readings</h2>
        
        <?php if (!empty($pending_readings)): ?>
            <button id="bulk-verify-btn" class="flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <i class="fas fa-check-circle mr-2"></i> Verify Selected
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (empty($pending_readings)): ?>
        <div class="p-6 text-center">
            <p class="text-gray-500">No pending meter readings found.</p>
        </div>
    <?php else: ?>
        <form action="verify_meter_readings.php" method="post" id="bulk-verify-form">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="w-12 px-3 py-3">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reading Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump/Nozzle</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opening Reading</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Reading</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pending_readings as $reading): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4">
                                        <input type="checkbox" name="selected_readings[]" value="<?= $reading['reading_id'] ?>" class="reading-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($reading['reading_date'])) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($reading['pump_name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Nozzle #<?= htmlspecialchars($reading['nozzle_number']) ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($reading['fuel_name']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= number_format($reading['opening_reading'], 2) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= number_format($reading['closing_reading'], 2) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= number_format($reading['volume_dispensed'], 2) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($reading['recorded_by_name']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button type="button" class="verify-btn text-green-600 hover:text-green-900" 
                                                data-reading-id="<?= $reading['reading_id'] ?>" 
                                                data-nozzle="<?= htmlspecialchars($reading['pump_name'] . ' - Nozzle #' . $reading['nozzle_number']) ?>"
                                                data-volume="<?= number_format($reading['volume_dispensed'], 2) ?>">
                                            <i class="fas fa-check-circle"></i> Verify
                                        </button>
                                        <button type="button" class="dispute-btn text-red-600 hover:text-red-900"
                                                data-reading-id="<?= $reading['reading_id'] ?>"
                                                data-nozzle="<?= htmlspecialchars($reading['pump_name'] . ' - Nozzle #' . $reading['nozzle_number']) ?>"
                                                data-volume="<?= number_format($reading['volume_dispensed'], 2) ?>">
                                            <i class="fas fa-times-circle"></i> Dispute
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <input type="hidden" name="bulk_verify" value="1">
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Verification Modal -->
<div id="verify-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800">Verify Meter Reading</h3>
        </div>
        
        <div class="p-6">
            <p class="mb-4">Are you sure you want to verify this meter reading?</p>
            
            <div class="bg-gray-50 p-4 rounded mb-4">
                <div class="flex justify-between mb-2">
                    <span class="text-sm text-gray-600">Nozzle:</span>
                    <span id="verify-nozzle" class="text-sm font-medium"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Volume Dispensed:</span>
                    <span id="verify-volume" class="text-sm font-medium"></span>
                </div>
            </div>
            
            <div class="text-sm text-gray-500 mb-4">
                <p><i class="fas fa-info-circle mr-1"></i> Verifying this reading will approve it and update related inventory records.</p>
            </div>
            
            <form action="verify_meter_readings.php" method="post" id="verify-form">
                <input type="hidden" name="reading_id" id="verify-reading-id">
                <div class="flex justify-end space-x-3">
                    <button type="button" class="cancel-btn px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="verify" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Verify Reading
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dispute Modal -->
<div id="dispute-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800">Dispute Meter Reading</h3>
        </div>
        
        <div class="p-6">
            <p class="mb-4">Please provide a reason for disputing this meter reading:</p>
            
            <div class="bg-gray-50 p-4 rounded mb-4">
                <div class="flex justify-between mb-2">
                    <span class="text-sm text-gray-600">Nozzle:</span>
                    <span id="dispute-nozzle" class="text-sm font-medium"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Volume Dispensed:</span>
                    <span id="dispute-volume" class="text-sm font-medium"></span>
                </div>
            </div>
            
            <form action="verify_meter_readings.php" method="post" id="dispute-form">
                <input type="hidden" name="reading_id" id="dispute-reading-id">
                
                <div class="mb-4">
                    <label for="dispute-notes" class="block text-sm font-medium text-gray-700 mb-1">Dispute Reason</label>
                    <textarea id="dispute-notes" name="dispute_notes" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" class="cancel-btn px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="dispute" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Dispute Reading
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verify Modal
    const verifyModal = document.getElementById('verify-modal');
    const verifyBtns = document.querySelectorAll('.verify-btn');
    const verifyForm = document.getElementById('verify-form');
    const verifyReadingId = document.getElementById('verify-reading-id');
    const verifyNozzle = document.getElementById('verify-nozzle');
    const verifyVolume = document.getElementById('verify-volume');
    
    // Dispute Modal
    const disputeModal = document.getElementById('dispute-modal');
    const disputeBtns = document.querySelectorAll('.dispute-btn');
    const disputeForm = document.getElementById('dispute-form');
    const disputeReadingId = document.getElementById('dispute-reading-id');
    const disputeNozzle = document.getElementById('dispute-nozzle');
    const disputeVolume = document.getElementById('dispute-volume');
    
    // Cancel buttons
    const cancelBtns = document.querySelectorAll('.cancel-btn');
    
    // Bulk verify
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.reading-checkbox');
    const bulkVerifyBtn = document.getElementById('bulk-verify-btn');
    const bulkVerifyForm = document.getElementById('bulk-verify-form');
    
    // Show verify modal
    verifyBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const readingId = this.getAttribute('data-reading-id');
            const nozzle = this.getAttribute('data-nozzle');
            const volume = this.getAttribute('data-volume');
            
            verifyReadingId.value = readingId;
            verifyNozzle.textContent = nozzle;
            verifyVolume.textContent = volume + ' liters';
            
            verifyModal.classList.remove('hidden');
        });
    });
    
    // Show dispute modal
    disputeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const readingId = this.getAttribute('data-reading-id');
            const nozzle = this.getAttribute('data-nozzle');
            const volume = this.getAttribute('data-volume');
            
            disputeReadingId.value = readingId;
            disputeNozzle.textContent = nozzle;
            disputeVolume.textContent = volume + ' liters';
            
            disputeModal.classList.remove('hidden');
        });
    });
    
    // Hide modals on cancel
    cancelBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            verifyModal.classList.add('hidden');
            disputeModal.classList.add('hidden');
        });
    });
    
    // Hide modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === verifyModal) {
            verifyModal.classList.add('hidden');
        }
        if (event.target === disputeModal) {
            disputeModal.classList.add('hidden');
        }
    });
    
    // Select all checkboxes
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkVerifyButton();
        });
    }
    
    // Update bulk verify button state
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkVerifyButton);
    });
    
    function updateBulkVerifyButton() {
        const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
        
        if (bulkVerifyBtn) {
            bulkVerifyBtn.disabled = !anyChecked;
        }
    }
    
    // Submit bulk verify form
    if (bulkVerifyBtn) {
        bulkVerifyBtn.addEventListener('click', function() {
            if (!this.disabled) {
                bulkVerifyForm.submit();
            }
        });
    }
});
</script>

<?php
// Include the footer
require_once '../../includes/footer.php';

ob_end_flush();
?>