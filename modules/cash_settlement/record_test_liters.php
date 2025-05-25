<?php
/**
 * Record Test Liters Adjustment
 * 
 * This file handles the process of recording test liters as tank adjustments
 * when a cash settlement is created or updated.
 */

// Include necessary files
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../modules/tank_management/functions.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Get input data
    $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
    $pump_id = isset($_POST['pump_id']) ? intval($_POST['pump_id']) : 0;
    $test_liters = isset($_POST['test_liters']) ? floatval($_POST['test_liters']) : 0;
    
    // Validate input
    $errors = [];
    if ($record_id <= 0) {
        $errors[] = 'Invalid record ID';
    }
    if ($pump_id <= 0) {
        $errors[] = 'Invalid pump ID';
    }
    if ($test_liters <= 0) {
        $errors[] = 'Test liters must be greater than zero';
    }
    
    if (!empty($errors)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
        } else {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">';
            echo '<p class="font-bold">Error</p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        exit;
    }
    
    // Call the function to record test liters adjustment
    $result = recordTestLitersAdjustment($pump_id, $test_liters, $record_id);
    
    if ($result) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Test liters adjustment recorded successfully',
                'test_liters' => $test_liters
            ]);
        } else {
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4">';
            echo '<p class="font-bold">Success</p>';
            echo '<p>Test liters adjustment recorded successfully.</p>';
            echo '</div>';
            
            // Redirect back to settlement details
            echo '<script>
                setTimeout(function() {
                    window.location.href = "settlement_details.php?id=' . $record_id . '";
                }, 2000);
            </script>';
        }
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to record test liters adjustment']);
        } else {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">';
            echo '<p class="font-bold">Error</p>';
            echo '<p>Failed to record test liters adjustment. Please check the logs for details.</p>';
            echo '</div>';
        }
    }
} else {
    // If not a POST request, display a form for manual adjustment
    $record_id = isset($_GET['record_id']) ? intval($_GET['record_id']) : 0;
    
    if ($record_id > 0) {
        // Get the record details
        $record = getCashRecordById($record_id);
        
        if (!$record) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">';
            echo '<p class="font-bold">Error</p>';
            echo '<p>Record not found.</p>';
            echo '</div>';
            exit;
        }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Test Liters Adjustment</title>
    <!-- Include your CSS files here -->
    <link rel="stylesheet" href="../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../assets/css/fontawesome.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Record Test Liters Adjustment</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold mb-4">
                Cash Record #<?= $record_id ?> - <?= htmlspecialchars($record['pump_name']) ?> 
                (<?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>)
            </h2>
            
            <form method="post" action="record_test_liters.php">
                <input type="hidden" name="record_id" value="<?= $record_id ?>">
                <input type="hidden" name="pump_id" value="<?= $record['pump_id'] ?>">
                
                <div class="mb-4">
                    <label for="test_liters" class="block text-sm font-medium text-gray-700 mb-1">
                        Test Liters to Adjust
                    </label>
                    <input type="number" id="test_liters" name="test_liters" 
                           value="<?= $record['test_liters'] ?? 0 ?>" step="0.01" min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <p class="mt-1 text-sm text-gray-500">
                        This amount will be added back to the tank.
                    </p>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Record Adjustment
                    </button>
                    <a href="settlement_details.php?id=<?= $record_id ?>" class="ml-4 text-blue-500 hover:underline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
    } else {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">';
        echo '<p class="font-bold">Error</p>';
        echo '<p>Record ID is required.</p>';
        echo '</div>';
    }
}
?>