@echo off
echo Creating directory structure for Petrol Pump Management System...

mkdir petrol_pump_management_system
cd petrol_pump_management_system

REM Create asset directories
mkdir assets
mkdir assets\css
mkdir assets\js
mkdir assets\images
mkdir assets\fonts

REM Create includes directory for common files
mkdir includes

REM Create module directories
mkdir modules
mkdir modules\fuel_ordering
mkdir modules\tank_management
mkdir modules\pump_management
mkdir modules\staff_management
mkdir modules\price_management
mkdir modules\cash_settlement
mkdir modules\attendance
mkdir modules\pos

REM Create API directory
mkdir api

REM Create reports directory
mkdir reports

echo Creating base PHP files...

REM Create core files
echo ^<?php // Database configuration ^?^> > includes\config.php
echo ^<?php // Database connection ^?^> > includes\db.php
echo ^<?php // Common functions ^?^> > includes\functions.php
echo ^<?php // Authentication functions ^?^> > includes\auth.php
echo ^<?php // Common header ^?^> > includes\header.php
echo ^<?php // Common footer ^?^> > includes\footer.php
echo ^<?php // Common sidebar ^?^> > includes\sidebar.php

REM Create main system files
echo ^<?php // Login page ^?^> > login.php
echo ^<?php // Logout script ^?^> > logout.php
echo ^<?php // Profile page ^?^> > profile.php
echo ^<?php // Settings page ^?^> > settings.php
echo ^<?php // Index page - entry point ^?^> > index.php

echo Creating module files...

REM 1. Fuel Ordering Module Files
echo ^<?php // Main page ^?^> > modules\fuel_ordering\index.php
echo ^<?php // Create new order ^?^> > modules\fuel_ordering\create_order.php
echo ^<?php // View all orders ^?^> > modules\fuel_ordering\view_orders.php
echo ^<?php // Order details ^?^> > modules\fuel_ordering\order_details.php
echo ^<?php // Update order ^?^> > modules\fuel_ordering\update_order.php
echo ^<?php // Module functions ^?^> > modules\fuel_ordering\functions.php

REM 2. Tank Management Module Files
echo ^<?php // Main page ^?^> > modules\tank_management\index.php
echo ^<?php // Add new tank ^?^> > modules\tank_management\add_tank.php
echo ^<?php // View all tanks ^?^> > modules\tank_management\view_tanks.php
echo ^<?php // Tank details ^?^> > modules\tank_management\tank_details.php
echo ^<?php // Update tank ^?^> > modules\tank_management\update_tank.php
echo ^<?php // Module functions ^?^> > modules\tank_management\functions.php

REM 3. Pump Management Module Files
echo ^<?php // Main page ^?^> > modules\pump_management\index.php
echo ^<?php // Add new pump ^?^> > modules\pump_management\add_pump.php
echo ^<?php // View all pumps ^?^> > modules\pump_management\view_pumps.php
echo ^<?php // Pump details ^?^> > modules\pump_management\pump_details.php
echo ^<?php // Update pump ^?^> > modules\pump_management\update_pump.php
echo ^<?php // Meter readings ^?^> > modules\pump_management\meter_reading.php
echo ^<?php // Module functions ^?^> > modules\pump_management\functions.php

REM 4. Staff Management Module Files
echo ^<?php // Main page ^?^> > modules\staff_management\index.php
echo ^<?php // Add new staff ^?^> > modules\staff_management\add_staff.php
echo ^<?php // View all staff ^?^> > modules\staff_management\view_staff.php
echo ^<?php // Staff details ^?^> > modules\staff_management\staff_details.php
echo ^<?php // Update staff ^?^> > modules\staff_management\update_staff.php
echo ^<?php // Assign staff to pumps ^?^> > modules\staff_management\assign_staff.php
echo ^<?php // Module functions ^?^> > modules\staff_management\functions.php

REM 5. Price Management Module Files
echo ^<?php // Main page ^?^> > modules\price_management\index.php
echo ^<?php // Add new price ^?^> > modules\price_management\add_price.php
echo ^<?php // View all prices ^?^> > modules\price_management\view_prices.php
echo ^<?php // Update price ^?^> > modules\price_management\update_price.php
echo ^<?php // Price impact analysis ^?^> > modules\price_management\price_analysis.php
echo ^<?php // Module functions ^?^> > modules\price_management\functions.php

REM 6. Cash Settlement Module Files
echo ^<?php // Main page ^?^> > modules\cash_settlement\index.php
echo ^<?php // Daily settlement ^?^> > modules\cash_settlement\daily_settlement.php
echo ^<?php // View all settlements ^?^> > modules\cash_settlement\view_settlements.php
echo ^<?php // Settlement details ^?^> > modules\cash_settlement\settlement_details.php
echo ^<?php // Module functions ^?^> > modules\cash_settlement\functions.php

REM 7. Attendance Module Files
echo ^<?php // Main page ^?^> > modules\attendance\index.php
echo ^<?php // Record attendance ^?^> > modules\attendance\record_attendance.php
echo ^<?php // View attendance ^?^> > modules\attendance\view_attendance.php
echo ^<?php // Overtime report ^?^> > modules\attendance\overtime_report.php
echo ^<?php // Module functions ^?^> > modules\attendance\functions.php

REM 8. POS Module Files
echo ^<?php // Main page ^?^> > modules\pos\index.php
echo ^<?php // Add new product ^?^> > modules\pos\add_product.php
echo ^<?php // View all products ^?^> > modules\pos\view_products.php
echo ^<?php // Update product ^?^> > modules\pos\update_product.php
echo ^<?php // Sales interface ^?^> > modules\pos\sales.php
echo ^<?php // Generate receipt ^?^> > modules\pos\receipt.php
echo ^<?php // Module functions ^?^> > modules\pos\functions.php

REM API Files
echo ^<?php // Fuel ordering API ^?^> > api\fuel_ordering_api.php
echo ^<?php // Tank management API ^?^> > api\tank_api.php
echo ^<?php // Pump management API ^?^> > api\pump_api.php
echo ^<?php // Staff management API ^?^> > api\staff_api.php
echo ^<?php // Price management API ^?^> > api\price_api.php
echo ^<?php // Cash settlement API ^?^> > api\cash_api.php
echo ^<?php // Attendance API ^?^> > api\attendance_api.php
echo ^<?php // POS API ^?^> > api\pos_api.php

REM Report Files
echo ^<?php // Fuel reports ^?^> > reports\fuel_reports.php
echo ^<?php // Tank reports ^?^> > reports\tank_reports.php
echo ^<?php // Pump reports ^?^> > reports\pump_reports.php
echo ^<?php // Staff reports ^?^> > reports\staff_reports.php
echo ^<?php // Financial reports ^?^> > reports\financial_reports.php
echo ^<?php // Attendance reports ^?^> > reports\attendance_reports.php
echo ^<?php // Sales reports ^?^> > reports\sales_reports.php

REM Create CSS files
echo /* Main stylesheet */ > assets\css\style.css
echo /* Login stylesheet */ > assets\css\login.css
echo /* Dashboard stylesheet */ > assets\css\dashboard.css

REM Create JS files
echo // Main JavaScript file > assets\js\main.js
echo // Form validation > assets\js\validation.js
echo // Chart display > assets\js\charts.js

echo Directory structure created successfully!
echo.
echo Next steps:
echo 1. Configure database connection in includes\config.php
echo 2. Set up authentication in includes\auth.php
echo 3. Implement the required functionality in each module

cd ..