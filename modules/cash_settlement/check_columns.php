<?php
/**
 * Database Structure Checker
 * 
 * This script displays the structure of the daily_cash_records table
 * to help identify the correct column names for the migration
 */

// Set page title and include header
$page_title = "Database Structure Check";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Cash Settlement</a> / Database Check';

// Include required files
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Check if user has admin permission
if (!has_permission('manage_cash') && $_SESSION['role'] != 'administrator') {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You need administrator privileges to run this check.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get table structure
$tables_query = "SHOW TABLES LIKE 'daily_cash_records'";
$tables_result = $conn->query($tables_query);

$structure = [];
$table_exists = false;

if ($tables_result && $tables_result->num_rows > 0) {
    $table_exists = true;
    $columns_query = "SHOW COLUMNS FROM daily_cash_records";
    $columns_result = $conn->query($columns_query);
    
    if ($columns_result) {
        while ($column = $columns_result->fetch_assoc()) {
            $structure[] = $column;
        }
    }
}

// Check for sample data to help identify columns
$sample_data = [];
if ($table_exists) {
    $sample_query = "SELECT * FROM daily_cash_records LIMIT 1";
    $sample_result = $conn->query($sample_query);
    
    if ($sample_result && $sample_result->num_rows > 0) {
        $sample_data = $sample_result->fetch_assoc();
    }
}
?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Database Structure Check</h2>
    
    <p class="mb-4 text-gray-700">
        This page displays the structure of your daily_cash_records table to help identify the correct column names for the test liters migration.
    </p>
    
    <?php if (!$table_exists): ?>
        <div class="mb-6 p-4 rounded bg-red-100 text-red-700">
            <p class="font-bold">Error</p>
            <p>The table 'daily_cash_records' does not exist in your database.</p>
        </div>
    <?php elseif (empty($structure)): ?>
        <div class="mb-6 p-4 rounded bg-red-100 text-red-700">
            <p class="font-bold">Error</p>
            <p>Unable to retrieve column information for table 'daily_cash_records'.</p>
        </div>
    <?php else: ?>
        <div class="mb-6">
            <h3 class="text-md font-semibold text-gray-800 mb-2">Table Structure</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Field
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Null
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Key
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Default
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Extra
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($structure as $column): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">
                                    <?= htmlspecialchars($column['Field']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($column['Type']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($column['Null']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($column['Key']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($column['Default'] ?? 'NULL') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($column['Extra']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (!empty($sample_data)): ?>
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-800 mb-2">Sample Record Data</h3>
                <p class="text-sm text-gray-600 mb-2">This might help identify which column contains test liters data.</p>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Column
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Value
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sample_data as $column => $value): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">
                                        <?= htmlspecialchars($column) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars(is_null($value) ? 'NULL' : $value) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-6">
            <h3 class="text-md font-semibold text-gray-800 mb-2">Next Steps</h3>
            <p class="text-sm text-gray-600 mb-4">
                After identifying the correct column names, update the migration script with these values:
            </p>
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <form action="update_test_liters.php" method="get">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="test_column" class="block text-sm font-medium text-gray-700">Test Liters Column Name:</label>
                            <select id="test_column" name="test_column" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <?php foreach ($structure as $column): ?>
                                    <option value="<?= htmlspecialchars($column['Field']) ?>"><?= htmlspecialchars($column['Field']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="price_column" class="block text-sm font-medium text-gray-700">Fuel Price Column Name (optional):</label>
                            <select id="price_column" name="price_column" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <option value="">None/Not Needed</option>
                                <?php foreach ($structure as $column): ?>
                                    <option value="<?= htmlspecialchars($column['Field']) ?>"><?= htmlspecialchars($column['Field']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Continue to Migration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-6">
        <a href="index.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> Return to Cash Settlement
        </a>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?>