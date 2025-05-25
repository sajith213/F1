<?php
/**
 * Pump Details
 * 
 * This page displays detailed information about a specific pump.
 */

// Set page title
$page_title = "Pump Details";
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / <a href="index.php" class="text-blue-600 hover:underline">Pump Management</a> / Pump Details';

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

// Check if pump ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>Invalid pump ID. <a href="index.php" class="text-red-900 underline">Return to pump management</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

$pump_id = (int)$_GET['id'];
$pump = getPumpById($pump_id);

// Check if pump exists
if (!$pump) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>Pump not found. <a href="index.php" class="text-red-900 underline">Return to pump management</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get list of tanks for the dropdown
$tanks = getTanks();

// Get list of fuel types
$fuel_types = getFuelTypes();

// Get associated tank details
$tank_details = null;
foreach ($tanks as $tank) {
    if ($tank['tank_id'] == $pump['tank_id']) {
        $tank_details = $tank;
        break;
    }
}

// Get nozzles for this pump
$nozzles = getNozzlesByPumpId($pump_id);

// Get recent meter readings for this pump's nozzles
$recent_readings = [];
if (!empty($nozzles)) {
    // Get the nozzle IDs
    $nozzle_ids = array_column($nozzles, 'nozzle_id');
    
    // Prepare a query to get recent readings for these nozzles
    $nozzle_id_list = implode(',', $nozzle_ids);
    $query = "SELECT mr.*, pn.nozzle_number, ft.fuel_name 
              FROM meter_readings mr
              JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              WHERE mr.nozzle_id IN ({$nozzle_id_list})
              ORDER BY mr.reading_date DESC, mr.created_at DESC
              LIMIT 10";
    
    $result = $conn->query($query);
    
    if ($result) {
        $recent_readings = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get staff assignments for this pump
$query = "SELECT sa.*, s.first_name, s.last_name
          FROM staff_assignments sa
          JOIN staff s ON sa.staff_id = s.staff_id
          WHERE sa.pump_id = {$pump_id}
          ORDER BY sa.assignment_date DESC
          LIMIT 10";

$result = $conn->query($query);
$staff_assignments = [];

if ($result) {
    $staff_assignments = $result->fetch_all(MYSQLI_ASSOC);
}

// Update the page title with the pump name
$page_title = "Pump Details: " . htmlspecialchars($pump['pump_name']);
?>

<!-- Main content -->
<div class="container mx-auto px-4 py-6">
    <!-- Action buttons -->
    <div class="flex justify-end mb-6">
        <a href="update_pump.php?id=<?php echo $pump_id; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
            <i class="fas fa-edit mr-2"></i> Edit Pump
        </a>
        <a href="meter_reading.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <i class="fas fa-tachometer-alt mr-2"></i> Record Meter Reading
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Pump Details Card -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden md:col-span-2">
            <div class="p-4 bg-blue-50 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-800">
                    <i class="fas fa-gas-pump mr-2"></i> Pump Information
                </h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Pump Name</h3>
                        <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($pump['pump_name']); ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Status</h3>
                        <p class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php 
                                switch ($pump['status']) {
                                    case 'active': echo 'bg-green-100 text-green-800'; break;
                                    case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                    case 'maintenance': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst($pump['status']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Associated Tank</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <?php if ($tank_details): ?>
                                <?php echo htmlspecialchars($tank_details['tank_name']); ?>
                                <span class="text-sm text-gray-500">
                                    (<?php echo htmlspecialchars($tank_details['fuel_name']); ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-red-600">No tank associated</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Model</h3>
                        <p class="mt-1 text-lg text-gray-900"><?php echo !empty($pump['model']) ? htmlspecialchars($pump['model']) : 'N/A'; ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Installation Date</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <?php echo !empty($pump['installation_date']) ? date('F d, Y', strtotime($pump['installation_date'])) : 'Not recorded'; ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Last Maintenance Date</h3>
                        <p class="mt-1 text-lg text-gray-900">
                            <?php 
                            if (!empty($pump['last_maintenance_date'])) {
                                $date = date('F d, Y', strtotime($pump['last_maintenance_date']));
                                $days = round((time() - strtotime($pump['last_maintenance_date'])) / (60 * 60 * 24));
                                echo $date . ' <span class="text-sm text-gray-500">(' . $days . ' days ago)</span>';
                            } else {
                                echo 'Not recorded';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($pump['notes'])): ?>
                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-gray-500">Notes</h3>
                        <div class="mt-1 p-3 bg-gray-50 rounded-md text-gray-700">
                            <?php echo nl2br(htmlspecialchars($pump['notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Nozzles Card -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-blue-50 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-800">
                    <i class="fas fa-fire-alt mr-2"></i> Nozzles
                </h2>
            </div>
            
            <div class="p-6">
                <?php if (empty($nozzles)): ?>
                    <div class="text-center text-gray-500 py-4">
                        <p>No nozzles found for this pump.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($nozzles as $nozzle): ?>
                            <div class="p-4 border rounded-md <?php echo $nozzle['status'] === 'active' ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'; ?>">
                                <div class="flex justify-between">
                                    <div>
                                        <h3 class="font-medium text-gray-900">Nozzle #<?php echo $nozzle['nozzle_number']; ?></h3>
                                        <p class="text-sm text-gray-500">ID: <?php echo $nozzle['nozzle_id']; ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        switch ($nozzle['status']) {
                                            case 'active': echo 'bg-green-100 text-green-800'; break;
                                            case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                            case 'maintenance': echo 'bg-yellow-100 text-yellow-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($nozzle['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="mt-2">
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
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Meter Readings Card -->
    <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-chart-line mr-2"></i> Recent Meter Readings
            </h2>
        </div>
        
        <div class="p-6">
            <?php if (empty($recent_readings)): ?>
                <div class="text-center text-gray-500 py-4">
                    <p>No meter readings found for this pump.</p>
                    <a href="meter_reading.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-tachometer-alt mr-2"></i> Record Meter Reading
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opening Reading</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Reading</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_readings as $reading): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($reading['reading_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        Nozzle #<?php echo htmlspecialchars($reading['nozzle_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch ($reading['fuel_name']) {
                                                case 'Petrol 92': echo 'bg-green-100 text-green-800'; break;
                                                case 'Petrol 95': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'Diesel': echo 'bg-red-100 text-red-800'; break;
                                                case 'Super Diesel': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($reading['fuel_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($reading['opening_reading'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($reading['closing_reading'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo number_format($reading['volume_dispensed'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch ($reading['verification_status']) {
                                                case 'verified': echo 'bg-green-100 text-green-800'; break;
                                                case 'disputed': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-yellow-100 text-yellow-800'; // pending
                                            }
                                            ?>">
                                            <?php echo ucfirst($reading['verification_status']); ?>
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
    
    <!-- Staff Assignments Card -->
    <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-users mr-2"></i> Recent Staff Assignments
            </h2>
        </div>
        
        <div class="p-6">
            <?php if (empty($staff_assignments)): ?>
                <div class="text-center text-gray-500 py-4">
                    <p>No staff assignments found for this pump.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($staff_assignments as $assignment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($assignment['assignment_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                <?php 
                                                $initials = strtoupper(substr($assignment['first_name'], 0, 1) . substr($assignment['last_name'], 0, 1));
                                                echo $initials;
                                                ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch ($assignment['shift']) {
                                                case 'morning': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'afternoon': echo 'bg-orange-100 text-orange-800'; break;
                                                case 'evening': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'night': echo 'bg-indigo-100 text-indigo-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($assignment['shift']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch ($assignment['status']) {
                                                case 'assigned': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'completed': echo 'bg-green-100 text-green-800'; break;
                                                case 'absent': echo 'bg-red-100 text-red-800'; break;
                                                case 'reassigned': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($assignment['notes']) ? htmlspecialchars($assignment['notes']) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>