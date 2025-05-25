<?php
/**
 * Header Template
 * 
 * Main header template for the Petrol Pump Management System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
// except for login page itself to avoid redirect loop
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page != 'login.php') {
    header("Location: login.php");
    exit;
}

// Get user data if logged in
$user_data = null;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    $stmt = $conn->prepare("SELECT user_id, username, full_name, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
}

// Get base path - handles nested directories correctly
$base_url = $app_url;

// Get company settings for header
$company_name = get_setting('company_name', 'Fuel Manager');
$current_date = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?><?= htmlspecialchars($company_name) ?></title>
    
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom styles -->
    <style>
        :root {
            --primary-color: <?= get_setting('primary_color', '#1e3a8a') ?>;
            --secondary-color: <?= get_setting('secondary_color', '#3b82f6') ?>;
            --header-color: <?= get_setting('header_color', '#1e3a8a') ?>;
            --footer-color: <?= get_setting('footer_color', '#ffffff') ?>;
            --sidebar-text-color: <?= get_setting('sidebar_text_color', '#ffffff') ?>;
            --sidebar-active-color: <?= get_setting('sidebar_active_color', '#1e40af') ?>;
            --sidebar-width: 14rem;
        }
        .bg-primary {
            background-color: var(--primary-color);
        }
        .text-primary {
            color: var(--primary-color);
        }
        .border-primary {
            border-color: var(--primary-color);
        }
        .bg-secondary {
            background-color: var(--secondary-color);
        }
        .text-sidebar {
            color: var(--sidebar-text-color);
        }
        .bg-header {
            background-color: var(--header-color);
        }
        .bg-footer {
            background-color: var(--footer-color);
        }
        .bg-sidebar-active {
            background-color: var(--sidebar-active-color);
        }
        
        /* Important layout fixes */
        #sidebar {
            width: var(--sidebar-width);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 50;
            background-color: var(--primary-color);
            color: var(--sidebar-text-color);
        }
        
        #main-content-wrapper {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
        }
        
        header.main-header {
            background-color: var(--header-color);
        }
        
        footer.main-footer {
            background-color: var(--footer-color);
        }
        
        @media (max-width: 1024px) {
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            #sidebar.active {
                transform: translateX(0);
            }
            
            #main-content-wrapper {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
    
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
</head>
<body class="bg-gray-100">
    <?php if ($current_page != 'login.php'): ?>
    <!-- Main container -->
    <div class="min-h-screen">
        <!-- Sidebar is included here -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main content wrapper -->
        <div id="main-content-wrapper">
            <!-- Top navbar -->
            <header class="bg-header shadow-md z-20 sticky top-0 main-header">
                <div class="flex justify-between items-center h-16 px-4">
                    <!-- Left side - Hamburger and title -->
                    <div class="flex items-center">
                        <button id="sidebar-toggle" class="text-white p-2 rounded-md hover:bg-sidebar-active focus:outline-none lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div class="ml-4 text-white text-xl font-semibold">
                            <?= htmlspecialchars($company_name) ?>
                        </div>
                    </div>
                    
                    <!-- Right side - Notifications and user dropdown -->
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="text-white p-2 rounded-full hover:bg-sidebar-active focus:outline-none">
                                <i class="fas fa-bell"></i>
                                <span class="absolute top-0 right-0 h-4 w-4 bg-red-500 rounded-full text-xs flex items-center justify-center">
                                    0
                                </span>
                            </button>
                        </div>
                        
                        <!-- User dropdown -->
                        <?php if ($user_data): ?>
                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center space-x-2 text-white focus:outline-none">
                                <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center">
                                    <?php 
                                    $initials = 'S';
                                    if (isset($user_data['full_name'])) {
                                        $name_parts = explode(' ', $user_data['full_name']);
                                        $initials = strtoupper(substr($name_parts[0], 0, 1));
                                        if (count($name_parts) > 1) {
                                            $initials = strtoupper(substr($name_parts[0], 0, 1));
                                        }
                                    }
                                    ?>
                                    <span class="text-sm font-bold"><?= $initials ?></span>
                                </div>
                                <span class="hidden md:inline"><?= htmlspecialchars($user_data['full_name']) ?></span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            
                            <!-- Dropdown menu -->
                            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-30">
                                <div class="py-1">
                                    <p class="px-4 py-2 text-sm text-gray-500">
                                        Signed in as <span class="font-medium text-gray-800"><?= htmlspecialchars($user_data['username']) ?></span>
                                    </p>
                                    <div class="border-t border-gray-200"></div>
                                    <a href="<?= $base_url ?>profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user mr-2"></i> Profile
                                    </a>
                                    <a href="<?= $base_url ?>settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-cog mr-2"></i> Settings
                                    </a>
                                    <div class="border-t border-gray-200"></div>
                                    <a href="<?= $base_url ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            
            <!-- Main content area -->
            <main class="p-4">
                <?php if (isset($page_title)): ?>
                <!-- Page header with title and breadcrumbs -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
                        <?php if (isset($breadcrumbs)): ?>
                        <div class="text-sm text-gray-500 mt-1">
                            <?= $breadcrumbs ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Date display -->
                    <div class="bg-blue-100 px-4 py-2 rounded-md text-blue-800 font-medium mt-4 md:mt-0">
                        <i class="far fa-calendar-alt mr-2"></i> <?= $current_date ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Page content starts here -->
    <?php endif; ?>