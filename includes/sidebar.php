<?php
/**
 * Sidebar Navigation
 * * This file provides the sidebar navigation for the Petrol Pump Management System.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect logic (same as before)
    $login_path = '';
    $current_script_depth = substr_count(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($current_script_depth > 1) {
        $login_path = str_repeat('../', $current_script_depth -1) . 'login.php';
    } else {
        $login_path = 'login.php';
    }
     if (file_exists(__DIR__ . '/config.php')) {
         include_once __DIR__ . '/config.php';
         if (isset($app_url)) {
            header("Location: " . rtrim($app_url, '/') . "/login.php");
            exit;
         }
     }
     error_log("Warning: \$app_url not defined. Using relative path for login redirect.");
     header("Location: " . $login_path);
     exit;
}

// Ensure getBaseUrl function exists (same as before)
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        if (isset($GLOBALS['app_url'])) {
           return rtrim($GLOBALS['app_url'], '/') . '/';
        }
        $script_name = $_SERVER['SCRIPT_NAME'];
        $base_path = str_replace(basename($script_name), '', $script_name);
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        $script_dir = dirname($_SERVER['SCRIPT_FILENAME']);
        $relative_path = str_replace($doc_root, '', $script_dir);
        $depth = substr_count(trim($relative_path, '/'), '/');
        if (strpos($script_dir, '/modules') !== false || strpos($script_dir, '/reports') !== false) {
             return str_repeat('../', $depth > 0 ? $depth : 1);
        } else {
             return './';
        }
    }
}
$base_url = getBaseUrl();

// Get current page details ONCE (same as before)
$current_script_path = $_SERVER['SCRIPT_FILENAME'];
$current_file = basename($current_script_path);
$current_dir = basename(dirname($current_script_path));
$app_root_dir_path = dirname(__DIR__);
$app_root_dir = basename($app_root_dir_path);

// --- Revised isActive Function ---
function isActive($page, $directory = null) {
    global $current_file, $current_dir, $app_root_dir;

    // Case 1: Link points to a specific file within a specific directory
    if ($directory !== null && $page !== 'index.php') {
        return ($page === $current_file && $directory === $current_dir);
    }

    // Case 2: Link points to the main index file of a module/directory
    // Activate if the current directory matches the link's directory.
    if ($directory !== null && $page === 'index.php') {
        return ($directory === $current_dir);
    }

    // Case 3: Link points to a file in the application's root directory OR reports directory
    if ($directory === null) {
        // Simple check if script path contains /reports/ - adjust if needed
        $is_in_reports_dir = (strpos(dirname($_SERVER['SCRIPT_FILENAME']), DIRECTORY_SEPARATOR . 'reports') !== false);
        $is_in_app_root = ($current_dir === $app_root_dir); // Check if in the app root

        if ($page === $current_file && ($is_in_app_root || $is_in_reports_dir)) {
             return true;
        }
    }

    return false; // Not active if none of the above conditions met
}
// --- End of Revised isActive Function ---


// --- isGroupActive Function: Checks if any link within a group is active ---
function isGroupActive($directories) {
     global $current_dir;
     // Ensure $directories is an array
     $directories = (array) $directories;
     return in_array($current_dir, $directories);
}
// --- End of isGroupActive Function ---


// Define user role for access control
$user_role = $_SESSION['role'] ?? 'guest';

// --- Check if user has permission (Refined for Clarity) ---
if (!function_exists('hasPermission')) {
    function hasPermission($module_key, $role) {
        // Define role-based access control more granularly if possible
        // Using simplified structure from previous example
        $permissions = [
            'admin' => ['all'],
            'manager' => ['all'],
            'cashier' => [
                'pos', 'cash_settlement', 'attendance', 'manage_petty_cash',
                'fuel_ordering', 'tank_management', 'pump_management', 'dip_management',
                'manage_petroleum_account', // Grant general access
                 // Specific salary permissions (if needed, otherwise covered by 'all'/'manager')
                 'view_salary_reports' // Cashier might only view reports/payslips?
            ],
            'attendant' => ['attendance', 'pump_management', 'dip_management'],
            // Add other roles as needed
        ];

         // Define module keys that map to specific permissions or groups
         $module_permissions = [
             'dashboard' => 'dashboard', // Or null if always allowed
             'suppliers' => 'fuel_ordering', // Part of fuel ordering
             'fuel_ordering' => 'fuel_ordering',
             'tank_management' => 'tank_management',
             'pump_management' => 'pump_management',
             'dip_management' => 'dip_management',
             'staff_management' => 'staff_management',
             'attendance' => 'attendance',
             'price_management' => 'price_management',
             'cash_settlement' => 'cash_settlement',
             'pos' => 'pos',
             'salary' => 'salary', // General key for the salary module group
             'loans' => 'manage_loans', // If loans has its own permission key
             'credit_management' => 'credit_management', // General key for credit group
             'credit_settlement' => 'credit_settlement',
             'petty_cash' => 'manage_petty_cash', // General key for petty cash
             'petroleum_account' => 'manage_petroleum_account', // General key
             'approve_petroleum_account' => 'approve_petroleum_account', // Specific permission
             'reports' => 'view_reports', // General key for reports group
             'settings' => 'system_settings',
             'users' => 'user_management',
             // Map specific pages if necessary
             'petty_cash_categories' => 'manage_petty_cash_categories',
             'petty_cash_reports' => 'view_petty_cash_reports',
             'credit_reports' => 'view_credit_reports', // Assuming a specific key if needed
             'salary_reports' => 'view_salary_reports',
             'salary_calculator' => 'calculate_salaries',
             'employee_salary' => 'manage_salaries',
             'payslip' => 'view_salary_reports', // Or manage_salaries?
             'epf_etf_report' => 'view_salary_reports',
         ];

         $effective_key = $module_permissions[$module_key] ?? $module_key; // Get the permission key

        // Check for 'all' access first
        if (isset($permissions[$role]) && in_array('all', $permissions[$role])) {
            return true;
        }

         // Check if the user has the specific permission key
         $has_specific_permission = isset($permissions[$role]) && in_array($effective_key, $permissions[$role]);

         // Special handling for Salary module group check (key 'salary')
          if ($module_key === 'salary') {
              if ($has_specific_permission) return true; // If they have the general 'salary' key (unlikely)
              // Check if they have ANY specific salary-related permission
              $salary_sub_perms = ['manage_salaries', 'calculate_salaries', 'manage_loans', 'view_salary_reports'];
              foreach ($salary_sub_perms as $sub_perm) {
                  if (isset($permissions[$role]) && in_array($sub_perm, $permissions[$role])) {
                       return true; // Has at least one salary permission
                   }
              }
              return false; // No salary permissions found
          }
           // Special handling for Petroleum Account Add Transaction
            if ($module_key === 'add_transaction.php' && $effective_key === 'manage_petroleum_account') {
                 // Allow if user has 'approve_petroleum_account'
                 return isset($permissions[$role]) && in_array('approve_petroleum_account', $permissions[$role]);
            }


        return $has_specific_permission; // Return based on the specific permission check
    }
}
// --- End hasPermission Function ---

// --- Helper function for generating links ---
function render_sidebar_link($href, $icon_class, $text, $page_name, $directory_name = null, $permission_key_override = null) {
    global $base_url, $user_role;

    // Determine the permission key
    $permission_key = $permission_key_override ?? $directory_name ?? $page_name;
    // Map specific pages to keys if needed
    if ($directory_name === 'salary') {
        if ($page_name === 'employee_salary.php') $permission_key = 'employee_salary';
        elseif ($page_name === 'salary_calculator.php') $permission_key = 'salary_calculator';
        elseif ($page_name === 'loans.php') $permission_key = 'loans';
        elseif ($page_name === 'payslip.php') $permission_key = 'payslip';
        elseif ($page_name === 'salary_report.php') $permission_key = 'salary_reports';
        elseif ($page_name === 'epf_etf_report.php') $permission_key = 'epf_etf_report';
        else $permission_key = 'salary'; // Default for index etc.
    } elseif ($directory_name === 'petty_cash') {
         if ($page_name === 'categories.php') $permission_key = 'petty_cash_categories';
         elseif ($page_name === 'reports.php') $permission_key = 'petty_cash_reports';
         else $permission_key = 'petty_cash';
    } elseif ($directory_name === 'petroleum_account') {
         if ($page_name === 'add_transaction.php') $permission_key = 'add_transaction.php'; // Special check handled in hasPermission
         else $permission_key = 'petroleum_account';
    } elseif ($directory_name === 'reports') {
         // For general reports, the key will be 'reports' (mapped to 'view_reports' in hasPermission)
         // If a specific report needs a different permission key, it could be handled here or by $permission_key_override
         if ($page_name === 'credit_reports.php') $permission_key = 'credit_reports'; // Already specific
         else $permission_key = 'reports'; // General reports permission
    }

    // Skip rendering if user doesn't have permission
    if ($permission_key !== 'dashboard' && !hasPermission($permission_key, $user_role)) {
        return ''; // Hide link completely
    }

    $active_class = isActive($page_name, $directory_name) ? 'bg-sidebar-active text-white' : 'text-sidebar hover:bg-sidebar-active hover:text-white';
    $full_href = rtrim($base_url, '/') . '/' . ltrim($href, '/');

    return <<<HTML
    <li>
        <a href="{$full_href}" class="flex items-center px-4 py-2 rounded-md {$active_class} transition">
            <i class="{$icon_class} w-5 h-5 mr-3"></i>
            <span>{$text}</span>
        </a>
    </li>
HTML;
}
// --- End render_sidebar_link Function ---

?>

<aside id="sidebar" class="bg-primary h-screen overflow-y-auto fixed left-0 top-0 bottom-0 z-50" style="width: var(--sidebar-width);">
    <div class="flex flex-col h-full">
        <div class="flex items-center p-4 flex-shrink-0">
            <div class="flex justify-center items-center h-10 w-10 rounded bg-white text-primary font-bold mr-3">
                PS
            </div>
            <span class="text-lg font-bold text-sidebar">Fuel Manager</span>
        </div>

        <nav class="flex-1 px-2 py-4 overflow-y-auto">
            <ul class="space-y-1">
                <?= render_sidebar_link('index.php', 'fas fa-tachometer-alt', 'Dashboard', 'index.php', null, 'dashboard') ?>

                <?= render_sidebar_link('modules/fuel_ordering/suppliers.php', 'fas fa-building', 'Suppliers', 'suppliers.php', 'fuel_ordering') ?>


                <?php
                 $inventory_active = isGroupActive(['fuel_ordering', 'tank_management', 'pump_management', 'dip_management']);
                 $can_see_inventory = hasPermission('fuel_ordering', $user_role) || hasPermission('tank_management', $user_role) || hasPermission('pump_management', $user_role) || hasPermission('dip_management', $user_role);
                 if ($can_see_inventory):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $inventory_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="inventory-submenu" aria-expanded="<?= $inventory_active ? 'true' : 'false' ?>">
                         <i class="fas fa-warehouse w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Inventory</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $inventory_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="inventory-submenu" class="pl-8 mt-1 space-y-1 <?= $inventory_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('modules/fuel_ordering/index.php', 'fas fa-gas-pump', 'Fuel Ordering', 'index.php', 'fuel_ordering') ?>
                         <?= render_sidebar_link('modules/tank_management/index.php', 'fas fa-oil-can', 'Tank Management', 'index.php', 'tank_management') ?>
                         <?= render_sidebar_link('modules/pump_management/index.php', 'fas fa-charging-station', 'Pump Management', 'index.php', 'pump_management') ?>
                         <?= render_sidebar_link('modules/dip_management/index.php', 'fas fa-ruler-vertical', 'Dip Management', 'index.php', 'dip_management') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                <?php
                 $hr_active = isGroupActive(['staff_management', 'attendance']);
                 $can_see_hr = hasPermission('staff_management', $user_role) || hasPermission('attendance', $user_role);
                 if ($can_see_hr):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $hr_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="hr-submenu" aria-expanded="<?= $hr_active ? 'true' : 'false' ?>">
                        <i class="fas fa-users-cog w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Human Resources</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $hr_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="hr-submenu" class="pl-8 mt-1 space-y-1 <?= $hr_active ? '' : 'hidden' ?>">
                        <?= render_sidebar_link('modules/staff_management/index.php', 'fas fa-users', 'Staff Management', 'index.php', 'staff_management') ?>
                        <?= render_sidebar_link('modules/attendance/index.php', 'fas fa-clock', 'Attendance', 'index.php', 'attendance') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                 <?php
                 $salary_active = isGroupActive('salary');
                 $can_see_salary = hasPermission('salary', $user_role); // General check for the group
                 if ($can_see_salary):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $salary_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="salary-submenu" aria-expanded="<?= $salary_active ? 'true' : 'false' ?>">
                         <i class="fas fa-coins w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Salary</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $salary_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="salary-submenu" class="pl-8 mt-1 space-y-1 <?= $salary_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('modules/salary/index.php', 'fas fa-money-bill-wave', 'Dashboard', 'index.php', 'salary') ?>
                         <?= render_sidebar_link('modules/salary/employee_salary.php', 'fas fa-user-cog', 'Employee Salaries', 'employee_salary.php', 'salary') ?>
                         <?= render_sidebar_link('modules/salary/salary_calculator.php', 'fas fa-calculator', 'Calculator', 'salary_calculator.php', 'salary') ?>
                         <?= render_sidebar_link('modules/salary/loans.php', 'fas fa-hand-holding-usd', 'Loans', 'loans.php', 'salary') ?>
                         <?= render_sidebar_link('modules/salary/payslip.php', 'fas fa-file-invoice-dollar', 'Payslips', 'payslip.php', 'salary') ?>
                         <?= render_sidebar_link('modules/salary/salary_report.php', 'fas fa-chart-pie', 'Salary Reports', 'salary_report.php', 'salary') ?>
                         <?= render_sidebar_link('modules/salary/epf_etf_report.php', 'fas fa-file-contract', 'EPF/ETF Reports', 'epf_etf_report.php', 'salary') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                <?php
                 $finance_active = isGroupActive(['price_management', 'cash_settlement', 'pos']);
                 $can_see_finance = hasPermission('price_management', $user_role) || hasPermission('cash_settlement', $user_role) || hasPermission('pos', $user_role);
                 if ($can_see_finance):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $finance_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="finance-submenu" aria-expanded="<?= $finance_active ? 'true' : 'false' ?>">
                         <i class="fas fa-dollar-sign w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Finance & Sales</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $finance_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="finance-submenu" class="pl-8 mt-1 space-y-1 <?= $finance_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('modules/price_management/index.php', 'fas fa-tags', 'Price Management', 'index.php', 'price_management') ?>
                         <?= render_sidebar_link('modules/cash_settlement/index.php', 'fas fa-cash-register', 'Cash Settlement', 'index.php', 'cash_settlement') ?>
                         <?= render_sidebar_link('modules/pos/index.php', 'fas fa-shopping-cart', 'Point of Sale', 'index.php', 'pos') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                <?php
                 $credit_active = isGroupActive(['credit_management', 'credit_settlement', 'reports']); // Include reports if credit_reports is active
                 // Adjust permission check: does user need 'credit_management' OR 'credit_settlement' OR 'view_credit_reports'?
                 $can_see_credit = hasPermission('credit_management', $user_role) || hasPermission('credit_settlement', $user_role) || hasPermission('credit_reports', $user_role);
                 if ($can_see_credit):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $credit_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="credit-submenu" aria-expanded="<?= $credit_active ? 'true' : 'false' ?>">
                        <i class="fas fa-credit-card w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Credit Management</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $credit_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="credit-submenu" class="pl-8 mt-1 space-y-1 <?= $credit_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('modules/credit_management/credit_customers.php', 'fas fa-address-book', 'Customers', 'credit_customers.php', 'credit_management') ?>
                         <?= render_sidebar_link('modules/credit_management/index.php', 'fas fa-file-invoice-dollar', 'Sales', 'index.php', 'credit_management') ?>
                         <?= render_sidebar_link('modules/credit_settlement/credit_settlements.php', 'fas fa-money-check-alt', 'Settlements', 'credit_settlements.php', 'credit_settlement') ?>
                         <?= render_sidebar_link('modules/reports/credit_reports.php', 'fas fa-chart-pie', 'Credit Reports', 'credit_reports.php', 'reports') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                <?php
                 $petty_cash_active = isGroupActive('petty_cash');
                 $can_see_petty_cash = hasPermission('petty_cash', $user_role); // Assuming 'manage_petty_cash' key covers viewing
                 if ($can_see_petty_cash):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $petty_cash_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="pettycash-submenu" aria-expanded="<?= $petty_cash_active ? 'true' : 'false' ?>">
                        <i class="fas fa-wallet w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Petty Cash</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $petty_cash_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="pettycash-submenu" class="pl-8 mt-1 space-y-1 <?= $petty_cash_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('modules/petty_cash/index.php', 'fas fa-receipt', 'Transactions', 'index.php', 'petty_cash') ?>
                         <?= render_sidebar_link('modules/petty_cash/categories.php', 'fas fa-tags', 'Categories', 'categories.php', 'petty_cash') ?>
                         <?= render_sidebar_link('modules/petty_cash/reports.php', 'fas fa-chart-bar', 'Reports', 'reports.php', 'petty_cash') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                <?php
                 $petrol_acct_active = isGroupActive('petroleum_account');
                 $can_see_petrol_acct = hasPermission('petroleum_account', $user_role) || hasPermission('approve_petroleum_account', $user_role);
                 if ($can_see_petrol_acct):
                 ?>
                 <li>
                    <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $petrol_acct_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="petrolacct-submenu" aria-expanded="<?= $petrol_acct_active ? 'true' : 'false' ?>">
                        <i class="fas fa-piggy-bank w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Petroleum Account</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $petrol_acct_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul id="petrolacct-submenu" class="pl-8 mt-1 space-y-1 <?= $petrol_acct_active ? '' : 'hidden' ?>">
                        <?= render_sidebar_link('modules/petroleum_account/index.php', 'fas fa-chart-line', 'Dashboard', 'index.php', 'petroleum_account') ?>
                        <?= render_sidebar_link('modules/petroleum_account/transactions.php', 'fas fa-exchange-alt', 'Transactions', 'transactions.php', 'petroleum_account') ?>
                        <?= render_sidebar_link('modules/petroleum_account/pending_topups.php', 'fas fa-exclamation-circle', 'Pending Top-ups', 'pending_topups.php', 'petroleum_account') ?>
                        <?= render_sidebar_link('modules/petroleum_account/add_transaction.php?type=deposit', 'fas fa-plus-circle', 'Add Transaction', 'add_transaction.php', 'petroleum_account') ?>
                    </ul>
                 </li>
                 <?php endif; ?>


                <?php
                 $reports_active = isGroupActive('reports');
                 $can_see_reports = hasPermission('reports', $user_role); // Check general view_reports permission
                 // Note: Credit Reports link is already handled within the Credit Management group logic if preferred
                 if ($can_see_reports):
                 ?>
                 <li>
                     <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $reports_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="reports-submenu" aria-expanded="<?= $reports_active ? 'true' : 'false' ?>">
                         <i class="fas fa-chart-area w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Reports</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $reports_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                     </button>
                     <ul id="reports-submenu" class="pl-8 mt-1 space-y-1 <?= $reports_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('reports/fuel_capacity_report.php', 'fas fa-chart-line', 'Fuel Capacity', 'fuel_capacity_report.php', 'reports') ?>
                         <?= render_sidebar_link('reports/daily_sales_report.php', 'fas fa-file-invoice-dollar', 'Sales Loss Profit', 'daily_sales_report.php', 'reports') ?>
                         <?= render_sidebar_link('reports/staff_attendance_report.php', 'fas fa-user-clock', 'Staff Attendance', 'staff_attendance_report.php', 'reports') ?>
                         
                         
                         <?= render_sidebar_link('reports/attendance_reports.php', 'fas fa-calendar-check', 'Attendance Overview', 'attendance_reports.php', 'reports') ?>
                         <?= render_sidebar_link('reports/customer_credit_history.php', 'fas fa-address-book', 'Customer Credit History', 'customer_credit_history.php', 'reports') ?>
                         <?= render_sidebar_link('reports/financial_reports.php', 'fas fa-balance-scale', 'Financial Reports', 'financial_reports.php', 'reports') ?>
                         <?= render_sidebar_link('reports/fuel_order_payment_report.php', 'fas fa-credit-card', 'Fuel Order Payments', 'fuel_order_payment_report.php', 'reports') ?>
                         <?= render_sidebar_link('reports/fuel_reports.php', 'fas fa-gas-pump', 'Fuel Overview', 'fuel_reports.php', 'reports') ?>
                         <?= render_sidebar_link('reports/outstanding_balances_report.php', 'fas fa-file-invoice', 'Outstanding Balances', 'outstanding_balances_report.php', 'reports') ?>
      
                         <?= render_sidebar_link('reports/price_impact_profit_loss_report.php', 'fas fa-chart-bar', 'Price Impact P&L', 'price_impact_profit_loss_report.php', 'reports') ?>
                         <?= render_sidebar_link('reports/pump_reports.php', 'fas fa-charging-station', 'Pump Reports', 'pump_reports.php', 'reports') ?>
                         <?= render_sidebar_link('reports/sales_reports.php', 'fas fa-shopping-cart', 'Sales Overview', 'sales_reports.php', 'reports') ?>
                         <?= render_sidebar_link('reports/staff_reports.php', 'fas fa-users', 'Staff Reports', 'staff_reports.php', 'reports') ?>
                         <?= render_sidebar_link('reports/tank_reports.php', 'fas fa-oil-can', 'Tank Reports', 'tank_reports.php', 'reports') ?>
                       

                         <?php // If Credit Reports link is NOT shown under Credit Management, show it here:
                            // echo render_sidebar_link('modules/reports/credit_reports.php', 'fas fa-chart-pie', 'Credit Reports', 'credit_reports.php', 'reports');
                         ?>
                     </ul>
                 </li>
                 <?php endif; ?>


                 <?php
                 $admin_active = isGroupActive(null); // Check based on files in root
                  $can_see_admin = hasPermission('settings', $user_role) || hasPermission('users', $user_role); // Check specific permissions
                 if ($can_see_admin):
                 ?>
                 <li>
                     <button type="button" class="flex items-center w-full px-4 py-2 rounded-md text-sidebar hover:bg-sidebar-active hover:text-white transition group sidebar-toggle <?= $admin_active ? 'bg-sidebar-active text-white' : '' ?>" aria-controls="admin-submenu" aria-expanded="<?= $admin_active ? 'true' : 'false' ?>">
                         <i class="fas fa-cogs w-5 h-5 mr-3"></i>
                         <span class="flex-1 text-left">Administration</span>
                         <svg class="w-4 h-4 ml-2 flex-shrink-0 transform transition-transform duration-200 ease-in-out <?= $admin_active ? 'rotate-180' : '' ?>" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                     </button>
                     <ul id="admin-submenu" class="pl-8 mt-1 space-y-1 <?= $admin_active ? '' : 'hidden' ?>">
                         <?= render_sidebar_link('settings.php', 'fas fa-cog', 'System Settings', 'settings.php', null, 'settings') ?>
                         <?= render_sidebar_link('users.php', 'fas fa-user-shield', 'User Management', 'users.php', null, 'users') ?>
                     </ul>
                 </li>
                 <?php endif; ?>

            </ul>
        </nav>

        <div class="border-t border-blue-800 mt-auto flex-shrink-0">
            <div class="p-4 flex items-center justify-between">
                 <div class="flex items-center overflow-hidden mr-2">
                     <div class="h-8 w-8 rounded-full bg-secondary flex items-center justify-center flex-shrink-0">
                         <span class="text-sm font-bold text-white">
                             <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
                         </span>
                     </div>
                     <div class="ml-3 overflow-hidden">
                         <p class="text-sm font-medium text-sidebar truncate" title="<?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>">
                             <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>
                         </p>
                         <p class="text-xs text-sidebar opacity-75 capitalize">
                             <?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?>
                         </p>
                     </div>
                 </div>
                 <a href="<?= rtrim($base_url, '/') ?>/logout.php" class="text-sidebar opacity-75 hover:opacity-100 flex-shrink-0" title="Logout">
                     <i class="fas fa-sign-out-alt"></i>
                 </a>
            </div>
        </div>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle');

    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('aria-controls');
            const targetSubmenu = document.getElementById(targetId);
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            const arrowIcon = this.querySelector('svg');

            if (targetSubmenu) {
                // Toggle submenu visibility
                targetSubmenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', !isExpanded);

                // Toggle arrow direction
                if (arrowIcon) {
                     arrowIcon.classList.toggle('rotate-180');
                }

                 // Optional: Add/Remove active background on parent toggle button if needed
                 // if (!isExpanded) { // If expanding
                 //     this.classList.add('bg-sidebar-active', 'text-white');
                 // } else { // If collapsing (only remove if no child is active - more complex)
                 //     let groupIsActive = false;
                 //     targetSubmenu.querySelectorAll('a').forEach(link => {
                 //         if (link.classList.contains('bg-sidebar-active')) {
                 //             groupIsActive = true;
                 //         }
                 //     });
                 //     if (!groupIsActive) {
                 //          this.classList.remove('bg-sidebar-active', 'text-white');
                 //     }
                 // }
            }
        });
    });
});
</script>