<?php
/**
 * Settings Page
 * 
 * This page allows administrators to configure system-wide settings
 * for the Petrol Pump Management System.
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$page_title = "System Settings";

// Set breadcrumbs
$breadcrumbs = '<a href="index.php" class="text-blue-600 hover:text-blue-800">Home</a> / System Settings';

// Include header
include_once 'includes/header.php';

// Check for permissions (only admin can access settings)
if ($user_data['role'] !== 'admin') {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this page. Only administrators can manage system settings.</p>
          </div>';
    include_once 'includes/footer.php';
    exit;
}

// Database connection
require_once 'includes/db.php';

// Define setting categories
$categories = [
    'company' => 'Company Information',
    'financial' => 'Financial Settings',
    'system' => 'System Configuration',
    'appearance' => 'Appearance',
    'backup' => 'Backup & Restore'
];

// Map settings to categories
$settings_map = [
    'company_name' => 'company',
    'company_address' => 'company',
    'company_phone' => 'company',
    'company_email' => 'company',
    'receipt_footer' => 'company',
    'currency_symbol' => 'financial',
    'vat_percentage' => 'financial',
    'shortage_allowance_percentage' => 'financial',
    'allow_negative_stock' => 'system',
    'theme_color' => 'appearance',
    'primary_color' => 'appearance',
    'secondary_color' => 'appearance',
    'header_color' => 'appearance',
    'footer_color' => 'appearance',
    'sidebar_text_color' => 'appearance',
    'sidebar_active_color' => 'appearance'
];

// Process form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Prepare update statement
        $update_query = "UPDATE system_settings SET setting_value = ? WHERE setting_name = ?";
        $stmt = $conn->prepare($update_query);
        
        // Process each setting
        foreach ($_POST['settings'] as $setting_name => $setting_value) {
            // Validate specific settings
            switch ($setting_name) {
                case 'company_email':
                    if (!empty($setting_value) && !filter_var($setting_value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Please enter a valid email address for company email.";
                        continue 2;
                    }
                    break;
                    
                case 'vat_percentage':
                case 'shortage_allowance_percentage':
                    if (!is_numeric($setting_value) || $setting_value < 0) {
                        $errors[] = "Please enter a valid number for " . str_replace('_', ' ', $setting_name) . ".";
                        continue 2;
                    }
                    break;
                    
                case 'currency_symbol':
                    if (empty($setting_value)) {
                        $errors[] = "Currency symbol cannot be empty.";
                        continue 2;
                    }
                    break;
                    
                case 'company_name':
                    if (empty($setting_value)) {
                        $errors[] = "Company name cannot be empty.";
                        continue 2;
                    }
                    break;
            }
            
            // Update setting
            $stmt->bind_param("ss", $setting_value, $setting_name);
            $stmt->execute();
        }
        
        // If no errors, commit transaction
        if (empty($errors)) {
            $conn->commit();
            $success_message = "Settings updated successfully.";
        } else {
            $conn->rollback();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Error updating settings: " . $e->getMessage();
    }
}

// Get all settings
$query = "SELECT * FROM system_settings ORDER BY setting_id";
$result = $conn->query($query);

$settings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category = isset($settings_map[$row['setting_name']]) ? $settings_map[$row['setting_name']] : 'system';
        if (!isset($settings[$category])) {
            $settings[$category] = [];
        }
        $settings[$category][] = $row;
    }
}

// Get backup history from database if the backup_history table exists
$backup_history = [];
$history_query = "SHOW TABLES LIKE 'backup_history'";
$table_exists = $conn->query($history_query);

if ($table_exists && $table_exists->num_rows > 0) {
    $history_query = "SELECT b.*, u.full_name FROM backup_history b 
                    LEFT JOIN users u ON b.created_by = u.user_id 
                    ORDER BY b.created_at DESC LIMIT 10";
    $history_result = $conn->query($history_query);
    
    if ($history_result && $history_result->num_rows > 0) {
        while ($row = $history_result->fetch_assoc()) {
            $backup_history[] = $row;
        }
    }
}

/**
 * Format file size in human-readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Generate a CSRF token
 * 
 * @param string $action Action name for this token
 * @return string CSRF token
 */
function generate_csrf_token($action) {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = array();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$action] = [
        'token' => $token,
        'time' => time()
    ];
    
    return $token;
}

/**
 * Output a CSRF token field
 * 
 * @param string $action Action name for this token
 */
function csrf_token_field($action) {
    $token = generate_csrf_token($action);
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
    echo '<input type="hidden" name="csrf_action" value="' . htmlspecialchars($action) . '">';
}

?>

<!-- Page content -->
<div class="container mx-auto px-4 py-4">
    
    <!-- Prominent Restore Success Message -->
    <?php if (isset($_GET['restore']) && $_GET['restore'] === 'success'): ?>
    <div id="restore-success-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-6 mb-6 text-center rounded-lg" role="alert">
        <div class="flex items-center justify-center">
            <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
            <div>
                <p class="font-bold text-lg">Database Restore Completed Successfully</p>
                <p class="text-sm mt-1">Your database has been successfully restored from the backup file.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Please fix the following errors:</p>
        <ul class="list-disc ml-5">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Regular settings form -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">System Settings</h2>
            <p class="text-sm text-gray-600">Configure global settings for the Petrol Pump Management System</p>
        </div>
        
        <form action="" method="POST" id="settings-form">
            <div class="p-6 space-y-6">
                <!-- Tabs for settings categories -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-6" id="settings-tabs">
                        <?php foreach ($categories as $category_key => $category_name): ?>
                        <a data-tab="<?= $category_key ?>" 
                           class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm cursor-pointer <?= $category_key === 'company' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>" 
                           href="#<?= $category_key ?>">
                            <?= htmlspecialchars($category_name) ?>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                
                <!-- Settings content -->
                <div class="space-y-6">
                    <?php foreach ($categories as $category_key => $category_name): ?>
                    <?php if ($category_key !== 'backup'): ?>
                    
                    <?php if ($category_key === 'appearance'): ?>
                    <!-- Appearance Settings Tab -->
                    <div id="tab-<?= $category_key ?>" class="tab-content space-y-4" style="display: none;">
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        Customize the appearance of your system by changing colors below. Changes will apply after saving settings.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Live Preview Panel -->
                        <div class="mb-6 border rounded-lg overflow-hidden">
                            <div class="text-sm font-medium text-gray-700 p-3 bg-gray-100 border-b">
                                Live Preview
                            </div>
                            <div class="p-4">
                                <div id="preview-container" class="border rounded overflow-hidden" style="height: 200px;">
                                    <!-- Mini Preview of the interface -->
                                    <div id="preview-header" class="h-10 bg-primary text-white flex items-center px-3 text-sm">
                                        Header
                                    </div>
                                    <div class="flex h-[calc(100%-40px)]">
                                        <div id="preview-sidebar" class="w-1/4 bg-primary h-full text-white p-3 text-sm">
                                            Sidebar
                                            <div id="preview-menu-item" class="mt-2 p-1 rounded-md bg-sidebar-active text-white text-xs">
                                                Selected Menu Item
                                            </div>
                                        </div>
                                        <div class="w-3/4 bg-gray-100 p-3 text-sm">
                                            Content Area
                                            <button id="preview-button" class="mt-2 px-2 py-1 bg-secondary text-white rounded-md text-xs">
                                                Button
                                            </button>
                                        </div>
                                    </div>
                                    <div id="preview-footer" class="h-6 bg-white border-t flex items-center justify-end px-3 text-gray-500 text-xs">
                                        Footer
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($settings['appearance'])): ?>
                            <?php foreach ($settings['appearance'] as $setting): ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                    <div>
                                        <label for="<?= $setting['setting_name'] ?>" class="block font-medium text-gray-700">
                                            <?= ucwords(str_replace('_', ' ', $setting['setting_name'])) ?>
                                        </label>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($setting['description'] ?? '') ?></p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <?php if (in_array($setting['setting_name'], ['primary_color', 'secondary_color', 'header_color', 'footer_color', 'sidebar_text_color', 'sidebar_active_color'])): ?>
                                            <!-- Color picker input -->
                                            <div class="flex items-center space-x-2">
                                                <input type="color" 
                                                    id="<?= $setting['setting_name'] ?>" 
                                                    name="settings[<?= $setting['setting_name'] ?>]" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                                    class="color-picker h-10 w-16 p-0 border-0 cursor-pointer"
                                                    data-preview-target="<?= $setting['setting_name'] ?>"
                                                >
                                                <input type="text" 
                                                    id="<?= $setting['setting_name'] ?>_text" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                                    class="w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                                    onchange="document.getElementById('<?= $setting['setting_name'] ?>').value = this.value; updatePreview();"
                                                >
                                                <button type="button" 
                                                    onclick="resetColor('<?= $setting['setting_name'] ?>', '<?= ($setting['setting_name'] === 'primary_color') ? '#1e3a8a' : (($setting['setting_name'] === 'secondary_color') ? '#3b82f6' : (($setting['setting_name'] === 'header_color') ? '#1e3a8a' : (($setting['setting_name'] === 'footer_color') ? '#ffffff' : (($setting['setting_name'] === 'sidebar_active_color') ? '#1e40af' : '#ffffff')))) ?>')" 
                                                    class="text-xs text-blue-600 hover:text-blue-800">
                                                    Reset to default
                                                </button>
                                            </div>
                                        <?php elseif ($setting['setting_name'] === 'theme_color'): ?>
                                            <!-- Theme selector dropdown -->
                                            <select id="<?= $setting['setting_name'] ?>" 
                                                    name="settings[<?= $setting['setting_name'] ?>]" 
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                                    onchange="applyPresetTheme(this.value)">
                                                <option value="blue" <?= $setting['setting_value'] === 'blue' ? 'selected' : '' ?>>Blue</option>
                                                <option value="green" <?= $setting['setting_value'] === 'green' ? 'selected' : '' ?>>Green</option>
                                                <option value="purple" <?= $setting['setting_value'] === 'purple' ? 'selected' : '' ?>>Purple</option>
                                                <option value="red" <?= $setting['setting_value'] === 'red' ? 'selected' : '' ?>>Red</option>
                                                <option value="dark" <?= $setting['setting_value'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                                                <option value="custom" <?= $setting['setting_value'] === 'custom' ? 'selected' : '' ?>>Custom</option>
                                            </select>
                                        <?php else: ?>
                                            <!-- Default text input for other appearance settings -->
                                            <input type="text" 
                                                id="<?= $setting['setting_name'] ?>" 
                                                name="settings[<?= $setting['setting_name'] ?>]" 
                                                value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-gray-500">
                                <p>No appearance settings found. Please add settings to the 'appearance' category.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php else: ?>
                    <!-- Other regular settings tabs -->
                    <div id="tab-<?= $category_key ?>" class="tab-content space-y-4" style="<?= $category_key === 'company' ? '' : 'display: none;' ?>">
                        <?php if (isset($settings[$category_key])): ?>
                            <?php foreach ($settings[$category_key] as $setting): ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div>
                                    <label for="<?= $setting['setting_name'] ?>" class="block font-medium text-gray-700">
                                        <?= ucwords(str_replace('_', ' ', $setting['setting_name'])) ?>
                                    </label>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($setting['description']) ?></p>
                                </div>
                                <div class="md:col-span-2">
                                    <?php if ($setting['setting_name'] === 'allow_negative_stock'): ?>
                                        <!-- Boolean setting as select -->
                                        <select id="<?= $setting['setting_name'] ?>" name="settings[<?= $setting['setting_name'] ?>]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <option value="true" <?= $setting['setting_value'] === 'true' ? 'selected' : '' ?>>Yes</option>
                                            <option value="false" <?= $setting['setting_value'] === 'false' ? 'selected' : '' ?>>No</option>
                                        </select>
                                    <?php elseif (in_array($setting['setting_name'], ['vat_percentage', 'shortage_allowance_percentage'])): ?>
                                        <!-- Numeric input with step -->
                                        <div class="flex items-center">
                                            <input type="number" id="<?= $setting['setting_name'] ?>" name="settings[<?= $setting['setting_name'] ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" step="0.01" min="0" class="w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                            <span class="ml-2">%</span>
                                        </div>
                                    <?php elseif ($setting['setting_name'] === 'currency_symbol'): ?>
                                        <!-- Short text input for currency symbol -->
                                        <input type="text" id="<?= $setting['setting_name'] ?>" name="settings[<?= $setting['setting_name'] ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" maxlength="5" class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <?php elseif ($setting['setting_name'] === 'company_address' || $setting['setting_name'] === 'receipt_footer'): ?>
                                        <!-- Textarea for longer text -->
                                        <textarea id="<?= $setting['setting_name'] ?>" name="settings[<?= $setting['setting_name'] ?>]" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                    <?php elseif ($setting['setting_name'] === 'company_email'): ?>
                                        <!-- Email input -->
                                        <input type="email" id="<?= $setting['setting_name'] ?>" name="settings[<?= $setting['setting_name'] ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <?php else: ?>
                                        <!-- Default text input -->
                                        <input type="text" id="<?= $setting['setting_name'] ?>" name="settings[<?= $setting['setting_name'] ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-gray-500">
                                <p>No settings found in this category.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Backup & Restore Tab (This is part of the tabs but not part of the settings form) -->
                    <div id="tab-backup" class="tab-content space-y-6" style="display: none;">
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong>Important:</strong> Backup files contain sensitive data. Store them securely.
                                        Restoring a database will replace all current data and cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Backup Section -->
                        <div class="border rounded-lg p-6 bg-white">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Create Database Backup</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Create a backup of your database. This will export all your data into a SQL file that you can download.
                            </p>
                            <a href="backup_handler.php?action=backup" target="_blank" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-download mr-2"></i>
                                Create Backup
                            </a>
                        </div>
                        
                        <!-- Backup History Section -->
                        <div class="border rounded-lg p-6 bg-white mt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Backup History</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Recent backup operations performed by administrators.
                            </p>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($backup_history)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No backup history available
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($backup_history as $backup): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= date('M d, Y H:i', strtotime($backup['created_at'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars(basename($backup['filename'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php if ($backup['is_restore']): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Restore
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            Backup
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($backup['full_name'] ?? 'Unknown') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= formatFileSize($backup['file_size']) ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button type="submit" name="update_settings" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
    
    <!-- Restore Section - SEPARATE form outside the main settings form -->
    <div id="restore-section" class="bg-white rounded-lg shadow-md overflow-hidden mt-6 <?= isset($_GET['tab']) && $_GET['tab'] === 'backup' ? 'block' : 'hidden' ?>">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Restore Database</h2>
            <p class="text-sm text-gray-600">Restore your database from a previous backup file</p>
        </div>
        
        <div class="p-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Warning:</strong> Restoring a database will replace all current data and cannot be undone.
                            Make sure you have a backup of your current data before proceeding.
                        </p>
                    </div>
                </div>
            </div>
            
            <form action="backup_handler.php" method="POST" enctype="multipart/form-data" id="restore-form">
                <input type="hidden" name="action" value="restore">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="backup_file">
                        Select Backup File (.sql)
                    </label>
                    <input type="file" id="backup_file" name="backup_file" 
                        class="block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-50 file:text-blue-700
                        hover:file:bg-blue-100" 
                        accept=".sql" required>
                </div>
                
                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="confirm_restore" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        <span class="ml-2 text-sm text-gray-700">I understand this will replace all current data and cannot be undone</span>
                    </label>
                </div>
                
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <i class="fas fa-upload mr-2"></i>
                    Restore Backup
                </button>
            </form>
        </div>
    </div>
</div>

<?php
// Add extra JavaScript for tabs and color preview
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tab switching functionality
    const tabs = document.querySelectorAll("#settings-tabs a");
    const tabContents = document.querySelectorAll(".tab-content");
    const restoreSection = document.getElementById("restore-section");
    
    tabs.forEach(tab => {
        tab.addEventListener("click", function(e) {
            e.preventDefault();
            
            // Get the tab id
            const tabId = this.getAttribute("data-tab");
            
            // Remove active class from all tabs
            tabs.forEach(t => {
                t.classList.remove("border-blue-500", "text-blue-600");
                t.classList.add("border-transparent", "text-gray-500", "hover:text-gray-700", "hover:border-gray-300");
            });
            
            // Add active class to clicked tab
            this.classList.add("border-blue-500", "text-blue-600");
            this.classList.remove("border-transparent", "text-gray-500", "hover:text-gray-700", "hover:border-gray-300");
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.style.display = "none";
            });
            
            // Show selected tab content
            document.getElementById("tab-" + tabId).style.display = "block";
            
            // Show/hide restore section based on selected tab
            if (tabId === "backup") {
                restoreSection.classList.remove("hidden");
            } else {
                restoreSection.classList.add("hidden");
            }
            
            // Initialize preview if appearance tab
            if (tabId === "appearance") {
                setTimeout(updatePreview, 100);
            }
        });
    });
    
    // Check if URL has a tab parameter to activate that tab
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get("tab");
    if (tabParam) {
        const tabLink = document.querySelector(`a[data-tab="${tabParam}"]`);
        if (tabLink) {
            tabLink.click();
        }
    }
    
    // Auto-hide success message after 10 seconds
    const restoreSuccessMessage = document.getElementById("restore-success-message");
    if (restoreSuccessMessage) {
        setTimeout(() => {
            restoreSuccessMessage.style.opacity = "1";
            let fadeEffect = setInterval(() => {
                if (parseFloat(restoreSuccessMessage.style.opacity) > 0) {
                    restoreSuccessMessage.style.opacity = (parseFloat(restoreSuccessMessage.style.opacity) - 0.1).toString();
                } else {
                    clearInterval(fadeEffect);
                    restoreSuccessMessage.style.display = "none";
                }
            }, 100);
        }, 10000); // Show for 10 seconds
    }
});

// Theme presets for color combinations
const themePresets = {
    blue: {
        primary_color: "#1e3a8a",
        secondary_color: "#3b82f6",
        header_color: "#1e3a8a",
        footer_color: "#ffffff",
        sidebar_text_color: "#ffffff",
        sidebar_active_color: "#1e40af"
    },
    green: {
        primary_color: "#166534",
        secondary_color: "#22c55e",
        header_color: "#166534",
        footer_color: "#ffffff",
        sidebar_text_color: "#ffffff",
        sidebar_active_color: "#15803d"
    },
    purple: {
        primary_color: "#581c87",
        secondary_color: "#a855f7",
        header_color: "#581c87",
        footer_color: "#ffffff",
        sidebar_text_color: "#ffffff",
        sidebar_active_color: "#7e22ce"
    },
    red: {
        primary_color: "#991b1b",
        secondary_color: "#ef4444",
        header_color: "#991b1b",
        footer_color: "#ffffff",
        sidebar_text_color: "#ffffff",
        sidebar_active_color: "#b91c1c"
    },
    dark: {
        primary_color: "#1f2937",
        secondary_color: "#4b5563",
        header_color: "#111827",
        footer_color: "#1f2937",
        sidebar_text_color: "#e5e7eb",
        sidebar_active_color: "#374151"
    }
};

function updatePreview() {
    // Get current color values
    const primaryColor = document.getElementById("primary_color")?.value || "#1e3a8a";
    const secondaryColor = document.getElementById("secondary_color")?.value || "#3b82f6";
    const headerColor = document.getElementById("header_color")?.value || "#1e3a8a";
    const footerColor = document.getElementById("footer_color")?.value || "#ffffff";
    const sidebarTextColor = document.getElementById("sidebar_text_color")?.value || "#ffffff";
    const sidebarActiveColor = document.getElementById("sidebar_active_color")?.value || "#1e40af";
    
    // Update preview elements
    const previewHeader = document.getElementById("preview-header");
    const previewSidebar = document.getElementById("preview-sidebar");
    const previewButton = document.getElementById("preview-button");
    const previewFooter = document.getElementById("preview-footer");
    const previewMenuItem = document.getElementById("preview-menu-item");
    
    if (previewHeader) previewHeader.style.backgroundColor = headerColor;
    if (previewSidebar) {
        previewSidebar.style.backgroundColor = primaryColor;
        previewSidebar.style.color = sidebarTextColor;
    }
    if (previewButton) previewButton.style.backgroundColor = secondaryColor;
    if (previewFooter) previewFooter.style.backgroundColor = footerColor;
    if (previewMenuItem) previewMenuItem.style.backgroundColor = sidebarActiveColor;
    
    // Update text field values
    const primaryColorText = document.getElementById("primary_color_text");
    const secondaryColorText = document.getElementById("secondary_color_text");
    const headerColorText = document.getElementById("header_color_text");
    const footerColorText = document.getElementById("footer_color_text");
    const sidebarTextColorText = document.getElementById("sidebar_text_color_text");
    const sidebarActiveColorText = document.getElementById("sidebar_active_color_text");
    
    if (primaryColorText) primaryColorText.value = primaryColor;
    if (secondaryColorText) secondaryColorText.value = secondaryColor;
    if (headerColorText) headerColorText.value = headerColor;
    if (footerColorText) footerColorText.value = footerColor;
    if (sidebarTextColorText) sidebarTextColorText.value = sidebarTextColor;
    if (sidebarActiveColorText) sidebarActiveColorText.value = sidebarActiveColor;
}

function resetColor(inputId, defaultColor) {
    document.getElementById(inputId).value = defaultColor;
    document.getElementById(inputId + "_text").value = defaultColor;
    updatePreview();
}

function applyPresetTheme(themeName) {
    // If custom theme selected, don\'t change colors
    if (themeName === "custom") {
        return;
    }
    
    // Get theme preset
    const preset = themePresets[themeName];
    if (!preset) return;
    
    // Apply preset colors
    for (const [setting, color] of Object.entries(preset)) {
        const element = document.getElementById(setting);
        const textElement = document.getElementById(setting + "_text");
        
        if (element) element.value = color;
        if (textElement) textElement.value = color;
    }
    
    // Update preview
    updatePreview();
}

// Setup input change listeners
document.addEventListener("DOMContentLoaded", function() {
    const colorPickers = document.querySelectorAll(".color-picker");
    colorPickers.forEach(picker => {
        picker.addEventListener("input", updatePreview);
    });
});
</script>
';

// Include footer
include_once 'includes/footer.php';
?>