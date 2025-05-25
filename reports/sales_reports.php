<?php
/**
 * Sales Reports
 * 
 * This file generates various reports related to sales data, product sales analysis, 
 * and other sales metrics for the petrol pump management system.
 */

// Set page title
$page_title = "Sales Reports";

// Include necessary files
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user has permission
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../includes/footer.php';
    exit;
}

// Get report type from URL parameter
$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get grouping option and additional filters
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';
$product_category = isset($_GET['product_category']) ? $_GET['product_category'] : null;
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'daily';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $group_by = $_POST['group_by'] ?? 'daily';
    $product_category = !empty($_POST['product_category']) ? $_POST['product_category'] : null;
    $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'group_by' => $group_by,
        'product_category' => $product_category,
        'staff_id' => $staff_id
    ]);
    
    header("Location: sales_reports.php?$query");
    exit;
}

// Function to get all product categories for dropdown
function getAllProductCategories($conn) {
    $sql = "SELECT 
                category_id,
                category_name,
                description
            FROM 
                product_categories
            WHERE 
                status = 'active'
            ORDER BY 
                category_name";
    
    $result = $conn->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

// Function to get all staff for dropdown
function getAllStaff($conn) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position
            FROM 
                staff s
            WHERE 
                s.status = 'active'
            ORDER BY 
                s.last_name, s.first_name";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    return $staff;
}

// Function to get daily sales data
function getDailySalesData($conn, $start_date, $end_date, $group_by, $staff_id = null) {
    $date_format = '%Y-%m-%d';
    $group_field = 'DATE(s.sale_date)';
    
    if ($group_by === 'weekly') {
        $date_format = '%x-%v'; // ISO year and week
        $group_field = "DATE_FORMAT(s.sale_date, '%x-%v')";
    } elseif ($group_by === 'monthly') {
        $date_format = '%Y-%m';
        $group_field = "DATE_FORMAT(s.sale_date, '%Y-%m')";
    } elseif ($group_by === 'yearly') {
        $date_format = '%Y';
        $group_field = "YEAR(s.sale_date)";
    } elseif ($group_by === 'hourly') {
        $date_format = '%H:00';
        $group_field = "HOUR(s.sale_date)";
    }
    
    $sql = "SELECT 
                $group_field as period,
                DATE_FORMAT(MIN(s.sale_date), '$date_format') as date_period,
                COUNT(s.sale_id) as transaction_count,
                SUM(s.total_amount) as total_amount,
                SUM(s.discount_amount) as discount_amount,
                SUM(s.tax_amount) as tax_amount,
                SUM(s.net_amount) as net_amount,
                COUNT(DISTINCT s.customer_phone) as unique_customers,
                AVG(s.net_amount) as average_sale,
                SUM(si.quantity) as total_items
            FROM 
                sales s
                LEFT JOIN sale_items si ON s.sale_id = si.sale_id
            WHERE 
                DATE(s.sale_date) BETWEEN ? AND ?";
    
    if ($staff_id) {
        $sql .= " AND s.staff_id = ?";
    }
    
    $sql .= " GROUP BY $group_field
              ORDER BY";
    
    if ($group_by === 'hourly') {
        $sql .= " HOUR(s.sale_date)";
    } else {
        $sql .= " period";
    }
    
    if ($staff_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $start_date, $end_date, $staff_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get product sales data
function getProductSalesData($conn, $start_date, $end_date, $product_category = null) {
    $sql = "SELECT 
                p.product_id,
                p.product_code,
                p.product_name,
                pc.category_name,
                COUNT(si.item_id) as sale_count,
                SUM(si.quantity) as quantity_sold,
                SUM(si.net_amount) as total_sales,
                AVG(si.unit_price) as average_price,
                SUM(si.quantity * p.purchase_price) as cost_price,
                SUM(si.net_amount - (si.quantity * p.purchase_price)) as profit_margin
            FROM 
                products p
                JOIN product_categories pc ON p.category_id = pc.category_id
                LEFT JOIN sale_items si ON p.product_id = si.product_id
                LEFT JOIN sales s ON si.sale_id = s.sale_id AND DATE(s.sale_date) BETWEEN ? AND ?
            WHERE 
                si.item_type = 'product'";
    
    if ($product_category) {
        $sql .= " AND p.category_id = ?";
    }
    
    $sql .= " GROUP BY p.product_id
              ORDER BY total_sales DESC";
    
    if ($product_category) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $start_date, $end_date, $product_category);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get fuel sales by type
function getFuelSalesData($conn, $start_date, $end_date, $group_by) {
    $date_format = '%Y-%m-%d';
    $group_field = 'DATE(s.sale_date)';
    
    if ($group_by === 'weekly') {
        $date_format = '%x-%v'; // ISO year and week
        $group_field = "DATE_FORMAT(s.sale_date, '%x-%v')";
    } elseif ($group_by === 'monthly') {
        $date_format = '%Y-%m';
        $group_field = "DATE_FORMAT(s.sale_date, '%Y-%m')";
    } elseif ($group_by === 'yearly') {
        $date_format = '%Y';
        $group_field = "YEAR(s.sale_date)";
    }
    
    $sql = "SELECT 
                ft.fuel_type_id,
                ft.fuel_name,
                $group_field as period,
                DATE_FORMAT(MIN(s.sale_date), '$date_format') as date_period,
                SUM(si.quantity) as volume_sold,
                SUM(si.net_amount) as total_sales,
                AVG(si.unit_price) as average_price
            FROM 
                sale_items si
                JOIN sales s ON si.sale_id = s.sale_id
                JOIN pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
            WHERE 
                si.item_type = 'fuel'
                AND DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY 
                ft.fuel_type_id, $group_field
            ORDER BY 
                ft.fuel_name, period";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get staff sales performance
function getStaffSalesPerformance($conn, $start_date, $end_date) {
    $sql = "SELECT 
                s.staff_id,
                s.staff_code,
                s.first_name,
                s.last_name,
                s.position,
                COUNT(sl.sale_id) as sale_count,
                SUM(sl.net_amount) as total_sales,
                AVG(sl.net_amount) as average_sale,
                MAX(sl.net_amount) as max_sale,
                SUM(CASE WHEN sl.sale_type = 'fuel' THEN sl.net_amount ELSE 0 END) as fuel_sales,
                SUM(CASE WHEN sl.sale_type = 'product' OR sl.sale_type = 'mixed' THEN sl.net_amount ELSE 0 END) as product_sales,
                COUNT(DISTINCT sl.customer_phone) as unique_customers
            FROM 
                staff s
                LEFT JOIN sales sl ON s.staff_id = sl.staff_id AND DATE(sl.sale_date) BETWEEN ? AND ?
            WHERE 
                s.status = 'active'
            GROUP BY 
                s.staff_id
            ORDER BY 
                total_sales DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Fetch data for dropdown filters
$all_product_categories = getAllProductCategories($conn);
$all_staff = getAllStaff($conn);

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'products':
        $report_data = getProductSalesData($conn, $start_date, $end_date, $product_category);
        $report_title = "Product Sales Analysis";
        break;
        
    case 'fuel':
        $report_data = getFuelSalesData($conn, $start_date, $end_date, $group_by);
        $report_title = "Fuel Sales Analysis";
        break;
        
    case 'staff':
        $report_data = getStaffSalesPerformance($conn, $start_date, $end_date);
        $report_title = "Staff Sales Performance";
        break;
        
    case 'daily':
    default:
        $report_data = getDailySalesData($conn, $start_date, $end_date, $group_by, $staff_id);
        $report_title = "Sales Trend Analysis";
        break;
}

// Calculate totals for summaries
$summary_totals = [];

if ($report_type === 'daily') {
    $summary_totals = [
        'transaction_count' => array_sum(array_column($report_data, 'transaction_count')),
        'total_amount' => array_sum(array_column($report_data, 'total_amount')),
        'discount_amount' => array_sum(array_column($report_data, 'discount_amount')),
        'tax_amount' => array_sum(array_column($report_data, 'tax_amount')),
        'net_amount' => array_sum(array_column($report_data, 'net_amount')),
        'unique_customers' => array_sum(array_column($report_data, 'unique_customers')),
        'total_items' => array_sum(array_column($report_data, 'total_items'))
    ];
} elseif ($report_type === 'products') {
    $summary_totals = [
        'sale_count' => array_sum(array_column($report_data, 'sale_count')),
        'quantity_sold' => array_sum(array_column($report_data, 'quantity_sold')),
        'total_sales' => array_sum(array_column($report_data, 'total_sales')),
        'cost_price' => array_sum(array_column($report_data, 'cost_price')),
        'profit_margin' => array_sum(array_column($report_data, 'profit_margin'))
    ];
} elseif ($report_type === 'fuel') {
    // Group by fuel type
    $fuel_sales = [];
    foreach ($report_data as $row) {
        $fuel_id = $row['fuel_type_id'];
        if (!isset($fuel_sales[$fuel_id])) {
            $fuel_sales[$fuel_id] = [
                'fuel_name' => $row['fuel_name'],
                'volume_sold' => 0,
                'total_sales' => 0
            ];
        }
        $fuel_sales[$fuel_id]['volume_sold'] += $row['volume_sold'];
        $fuel_sales[$fuel_id]['total_sales'] += $row['total_sales'];
    }
    
    $summary_totals = [
        'volume_sold' => array_sum(array_column($report_data, 'volume_sold')),
        'total_sales' => array_sum(array_column($report_data, 'total_sales')),
        'fuel_sales' => $fuel_sales
    ];
} elseif ($report_type === 'staff') {
    $summary_totals = [
        'sale_count' => array_sum(array_column($report_data, 'sale_count')),
        'total_sales' => array_sum(array_column($report_data, 'total_sales')),
        'fuel_sales' => array_sum(array_column($report_data, 'fuel_sales')),
        'product_sales' => array_sum(array_column($report_data, 'product_sales')),
        'unique_customers' => array_sum(array_column($report_data, 'unique_customers'))
    ];
}

?>

<!-- Filter form -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="POST" action="" class="w-full">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>Sales Trend</option>
                    <option value="products" <?= $report_type === 'products' ? 'selected' : '' ?>>Product Sales</option>
                    <option value="fuel" <?= $report_type === 'fuel' ? 'selected' : '' ?>>Fuel Sales</option>
                    <option value="staff" <?= $report_type === 'staff' ? 'selected' : '' ?>>Staff Performance</option>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div class="<?= in_array($report_type, ['daily', 'fuel']) ? '' : 'hidden' ?>" id="group-by-container">
                <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                <select id="group_by" name="group_by" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <?php if ($report_type === 'daily'): ?>
                    <option value="hourly" <?= $group_by === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                    <?php endif; ?>
                    <option value="daily" <?= $group_by === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $group_by === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $group_by === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="yearly" <?= $group_by === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                </select>
            </div>
            
            <div class="<?= $report_type === 'products' ? '' : 'hidden' ?>" id="category-filter-container">
                <label for="product_category" class="block text-sm font-medium text-gray-700 mb-1">Product Category</label>
                <select id="product_category" name="product_category" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Categories</option>
                    <?php foreach ($all_product_categories as $category): ?>
                        <option value="<?= $category['category_id'] ?>" <?= $product_category == $category['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="<?= $report_type === 'daily' ? '' : 'hidden' ?>" id="staff-filter-container">
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                <select id="staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): ?>
                        <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex items-end mt-4 md:mt-0">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php if ($report_type === 'daily'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Transactions</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['transaction_count']) ?></div>
            <div class="mt-2 text-xs text-gray-500">For the selected period</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Net Sales</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['net_amount'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Total sales amount</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Avg. Transaction</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= $summary_totals['transaction_count'] > 0 ? 
                    CURRENCY_SYMBOL . number_format($summary_totals['net_amount'] / $summary_totals['transaction_count'], 2) : 
                    CURRENCY_SYMBOL . '0.00' ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Per transaction</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Unique Customers</div>
            <div class="text-2xl font-bold text-indigo-600"><?= number_format($summary_totals['unique_customers']) ?></div>
            <div class="mt-2 text-xs text-gray-500">Based on phone numbers</div>
        </div>
        
    <?php elseif ($report_type === 'products'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Products Sold</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['quantity_sold']) ?></div>
            <div class="mt-2 text-xs text-gray-500">Units sold</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Sales</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_sales'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">From product sales</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Cost Price</div>
            <div class="text-2xl font-bold text-red-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['cost_price'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Total cost of goods sold</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Profit Margin</div>
            <div class="text-2xl font-bold text-blue-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['profit_margin'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">
                <?= number_format(($summary_totals['profit_margin'] / $summary_totals['total_sales']) * 100, 2) ?>% margin
            </div>
        </div>
        
    <?php elseif ($report_type === 'fuel'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Volume Sold</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['volume_sold'], 2) ?> L</div>
            <div class="mt-2 text-xs text-gray-500">For the selected period</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Sales</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_sales'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">From fuel sales</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Avg. Price per Liter</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= $summary_totals['volume_sold'] > 0 ? 
                    CURRENCY_SYMBOL . number_format($summary_totals['total_sales'] / $summary_totals['volume_sold'], 2) : 
                    CURRENCY_SYMBOL . '0.00' ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Blended average</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Fuel Types</div>
            <div class="text-2xl font-bold text-indigo-600"><?= count($summary_totals['fuel_sales']) ?></div>
            <div class="mt-2 text-xs text-gray-500">Available fuel types</div>
        </div>
        
    <?php elseif ($report_type === 'staff'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Active Staff</div>
            <div class="text-2xl font-bold text-gray-800"><?= count($report_data) ?></div>
            <div class="mt-2 text-xs text-gray-500">Sales staff</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Sales</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_sales'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">All staff combined</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Avg. per Staff</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= count($report_data) > 0 ? 
                    CURRENCY_SYMBOL . number_format($summary_totals['total_sales'] / count($report_data), 2) : 
                    CURRENCY_SYMBOL . '0.00' ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Average sales per staff</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Transactions</div>
            <div class="text-2xl font-bold text-indigo-600"><?= number_format($summary_totals['sale_count']) ?></div>
            <div class="mt-2 text-xs text-gray-500">For the selected period</div>
        </div>
    <?php endif; ?>
</div>

<!-- Report header with export buttons -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0"><?= $report_title ?></h2>
        
        <div class="flex space-x-2">
            <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-print mr-2"></i> Print
            </button>
            <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-pdf mr-2"></i> PDF
            </button>
            <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-excel mr-2"></i> Excel
            </button>
        </div>
    </div>
</div>

<!-- Report content - changes based on report type -->
<div class="bg-white rounded-lg shadow-md overflow-hidden" id="report-content">
    <?php if (empty($report_data)): ?>
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
            <p>No data found for the selected criteria.</p>
        </div>
    <?php elseif ($report_type === 'daily'): ?>
        <!-- Sales Trend Analysis Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discounts</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tax</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Transaction</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Items Sold</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php
                                    if ($group_by === 'hourly') {
                                        echo $row['date_period'];
                                    } elseif ($group_by === 'daily') {
                                        echo date('M d, Y', strtotime($row['date_period']));
                                    } elseif ($group_by === 'weekly') {
                                        $parts = explode('-', $row['date_period']);
                                        echo "Week {$parts[1]}, {$parts[0]}";
                                    } elseif ($group_by === 'monthly') {
                                        echo date('M Y', strtotime($row['date_period'] . '-01'));
                                    } else {
                                        echo $row['date_period'];
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['transaction_count']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['total_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['discount_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['tax_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= CURRENCY_SYMBOL . number_format($row['net_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= $row['transaction_count'] > 0 ? 
                                    CURRENCY_SYMBOL . number_format($row['net_amount'] / $row['transaction_count'], 2) : 
                                    '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_items']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['transaction_count']) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['discount_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['tax_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['net_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600">
                            <?= $summary_totals['transaction_count'] > 0 ? 
                                CURRENCY_SYMBOL . number_format($summary_totals['net_amount'] / $summary_totals['transaction_count'], 2) : 
                                '-' ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['total_items']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'products'): ?>
        <!-- Product Sales Analysis Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity Sold</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Price</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Price</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit Margin</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): 
                        $profit_percentage = $row['total_sales'] > 0 ? ($row['profit_margin'] / $row['total_sales']) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['product_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['product_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['category_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['quantity_sold']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= CURRENCY_SYMBOL . number_format($row['total_sales'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['average_price'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['cost_price'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                <?= CURRENCY_SYMBOL . number_format($row['profit_margin'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= number_format($profit_percentage, 2) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['quantity_sold']) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_sales'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">-</td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['cost_price'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['profit_margin'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600">
                            <?= $summary_totals['total_sales'] > 0 ? 
                                number_format(($summary_totals['profit_margin'] / $summary_totals['total_sales']) * 100, 2) . '%' : 
                                '0.00%' ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($report_type === 'fuel'): ?>
        <!-- Fuel Sales Analysis Report -->
        
        <!-- Fuel Type Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4">
            <?php foreach ($summary_totals['fuel_sales'] as $fuel): 
                $percentage = $summary_totals['volume_sold'] > 0 ? 
                             ($fuel['volume_sold'] / $summary_totals['volume_sold']) * 100 : 0;
            ?>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-lg font-medium text-gray-900"><?= htmlspecialchars($fuel['fuel_name']) ?></div>
                            <div class="text-xs text-gray-500">Volume Sold</div>
                            <div class="text-xl font-bold text-blue-600 mt-1"><?= number_format($fuel['volume_sold'], 2) ?> L</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Sales Amount</div>
                            <div class="text-lg font-medium text-green-600"><?= CURRENCY_SYMBOL . number_format($fuel['total_sales'], 2) ?></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <div class="text-xs text-right mt-1"><?= number_format($percentage, 1) ?>% of total volume</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Detailed Fuel Sales Table -->
        <div class="overflow-x-auto mt-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Sold (L)</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Price/L</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php
                                    if ($group_by === 'daily') {
                                        echo date('M d, Y', strtotime($row['date_period']));
                                    } elseif ($group_by === 'weekly') {
                                        $parts = explode('-', $row['date_period']);
                                        echo "Week {$parts[1]}, {$parts[0]}";
                                    } elseif ($group_by === 'monthly') {
                                        echo date('M Y', strtotime($row['date_period'] . '-01'));
                                    } else {
                                        echo $row['date_period'];
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($row['fuel_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['volume_sold'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= CURRENCY_SYMBOL . number_format($row['total_sales'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= CURRENCY_SYMBOL . number_format($row['average_price'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($report_type === 'staff'): ?>
        <!-- Staff Sales Performance Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Transaction</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Sales</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Product Sales</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Customers</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Distribution</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    foreach ($report_data as $row): 
                        $sales_percentage = $summary_totals['total_sales'] > 0 ? 
                                          ($row['total_sales'] / $summary_totals['total_sales']) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['staff_code']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['position']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= CURRENCY_SYMBOL . number_format($row['total_sales'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['sale_count']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= $row['sale_count'] > 0 ? 
                                    CURRENCY_SYMBOL . number_format($row['average_sale'], 2) : 
                                    '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['fuel_sales'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['product_sales'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['unique_customers']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $sales_percentage ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($sales_percentage, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_sales'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['sale_count']) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600">
                            <?= $summary_totals['sale_count'] > 0 ? 
                                CURRENCY_SYMBOL . number_format($summary_totals['total_sales'] / $summary_totals['sale_count'], 2) : 
                                '-' ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['fuel_sales'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['product_sales'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['unique_customers']) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-500">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for filter handling and export functionality -->
<script>
    // Toggle form fields based on report type
    document.getElementById('report_type').addEventListener('change', function() {
        const reportType = this.value;
        
        // Group By field visibility
        const groupByContainer = document.getElementById('group-by-container');
        if (reportType === 'daily' || reportType === 'fuel') {
            groupByContainer.classList.remove('hidden');
        } else {
            groupByContainer.classList.add('hidden');
        }
        
        // Category filter visibility
        const categoryFilterContainer = document.getElementById('category-filter-container');
        if (reportType === 'products') {
            categoryFilterContainer.classList.remove('hidden');
        } else {
            categoryFilterContainer.classList.add('hidden');
        }
        
        // Staff filter visibility
        const staffFilterContainer = document.getElementById('staff-filter-container');
        if (reportType === 'daily') {
            staffFilterContainer.classList.remove('hidden');
        } else {
            staffFilterContainer.classList.add('hidden');
        }
    });
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library like TCPDF or FPDF
    }
    
    // Export to Excel
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require PhpSpreadsheet or similar library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>