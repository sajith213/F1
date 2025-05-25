<?php
/**
 * Fuel Credit Sales Dashboard
 * 
 * This script provides a dashboard and detailed view for fuel credit sales
 */

// Include required files
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today
$showAllRecords = isset($_GET['show_all']) && $_GET['show_all'] == 1;

// CSS styles for the dashboard
echo "
<style>
    .dashboard-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    .card {
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        padding: 15px;
    }
    .summary-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }
    .summary-card {
        flex: 1;
        min-width: 200px;
        text-align: center;
        padding: 15px;
        border-radius: 5px;
    }
    .summary-card.sales {
        background-color: #e3f2fd;
    }
    .summary-card.revenue {
        background-color: #e8f5e9;
    }
    .summary-card.pending {
        background-color: #fff8e1;
    }
    .summary-card.overdue {
        background-color: #ffebee;
    }
    .summary-card h3 {
        margin-top: 0;
        font-size: 16px;
    }
    .summary-card .amount {
        font-size: 24px;
        font-weight: bold;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    table th, table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    table th {
        background-color: #f5f5f5;
    }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
        align-items: flex-end;
    }
    .filter-form div {
        display: flex;
        flex-direction: column;
    }
    .filter-form label {
        margin-bottom: 5px;
        font-weight: bold;
    }
    .filter-form select, .filter-form input {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .filter-form button {
        padding: 8px 15px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    .action-buttons a {
        display: inline-block;
        margin-right: 5px;
        padding: 5px 10px;
        background-color: #2196F3;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 12px;
    }
    .action-buttons a.view {
        background-color: #2196F3;
    }
    .action-buttons a.edit {
        background-color: #FFC107;
    }
    .action-buttons a.delete {
        background-color: #F44336;
    }
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    .pagination a {
        padding: 8px 12px;
        margin: 0 5px;
        border: 1px solid #ddd;
        text-decoration: none;
    }
    .pagination a.active {
        background-color: #4CAF50;
        color: white;
        border: 1px solid #4CAF50;
    }
</style>";

echo "<div class='dashboard-container'>";
echo "<h1>Fuel Credit Sales Dashboard</h1>";

// Function to get summary statistics
function getSummaryStats($conn, $dateFrom, $dateTo, $customerId = null) {
    $where = "WHERE (s.sale_type = 'fuel' OR s.sale_type = 'petroleum') AND s.sale_date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];
    $types = "ss";
    
    if ($customerId) {
        $where .= " AND cs.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    
    // Total sales
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_sales, SUM(cs.credit_amount) as total_amount
        FROM credit_sales cs
        JOIN sales s ON cs.sale_id = s.sale_id
        $where
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    // Pending amount
    $stmt = $conn->prepare("
        SELECT SUM(cs.remaining_amount) as pending_amount
        FROM credit_sales cs
        JOIN sales s ON cs.sale_id = s.sale_id
        $where AND cs.status = 'pending'
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending = $result->fetch_assoc();
    $stats['pending_amount'] = $pending['pending_amount'] ?? 0;
    $stmt->close();
    
    // Overdue amount
    $stmt = $conn->prepare("
        SELECT SUM(cs.remaining_amount) as overdue_amount
        FROM credit_sales cs
        JOIN sales s ON cs.sale_id = s.sale_id
        $where AND cs.status = 'pending' AND cs.due_date < CURDATE()
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $overdue = $result->fetch_assoc();
    $stats['overdue_amount'] = $overdue['overdue_amount'] ?? 0;
    $stmt->close();
    
    return $stats;
}

// Display filter form
echo "<div class='card'>";
echo "<form class='filter-form' method='get'>";

echo "<div>";
echo "<label for='customer_id'>Customer:</label>";
echo "<select name='customer_id' id='customer_id'>";
echo "<option value=''>All Customers</option>";

$result = $conn->query("SELECT customer_id, customer_name FROM credit_customers ORDER BY customer_name");
while ($row = $result->fetch_assoc()) {
    $selected = ($customerId == $row['customer_id']) ? 'selected' : '';
    echo "<option value='{$row['customer_id']}' $selected>{$row['customer_name']}</option>";
}

echo "</select>";
echo "</div>";

echo "<div>";
echo "<label for='date_from'>From Date:</label>";
echo "<input type='date' id='date_from' name='date_from' value='$dateFrom'>";
echo "</div>";

echo "<div>";
echo "<label for='date_to'>To Date:</label>";
echo "<input type='date' id='date_to' name='date_to' value='$dateTo'>";
echo "</div>";

echo "<div>";
$checked = $showAllRecords ? 'checked' : '';
echo "<label for='show_all'>Show All Records:</label>";
echo "<input type='checkbox' id='show_all' name='show_all' value='1' $checked>";
echo "</div>";

echo "<button type='submit'>Apply Filters</button>";
echo "</form>";
echo "</div>";

// Get summary statistics
$stats = getSummaryStats($conn, $dateFrom, $dateTo, $customerId);

// Display summary cards
echo "<div class='summary-cards'>";

echo "<div class='summary-card sales'>";
echo "<h3>Total Fuel Credit Sales</h3>";
echo "<div class='amount'>" . number_format($stats['total_sales'] ?? 0) . "</div>";
echo "</div>";

echo "<div class='summary-card revenue'>";
echo "<h3>Total Credit Amount</h3>";
echo "<div class='amount'>Rs. " . number_format($stats['total_amount'] ?? 0, 2) . "</div>";
echo "</div>";

echo "<div class='summary-card pending'>";
echo "<h3>Pending Amount</h3>";
echo "<div class='amount'>Rs. " . number_format($stats['pending_amount'] ?? 0, 2) . "</div>";
echo "</div>";

echo "<div class='summary-card overdue'>";
echo "<h3>Overdue Amount</h3>";
echo "<div class='amount'>Rs. " . number_format($stats['overdue_amount'] ?? 0, 2) . "</div>";
echo "</div>";

echo "</div>";

// Function to display fuel credit sales
function displayFuelCreditSales($conn, $dateFrom, $dateTo, $customerId = null, $showAllRecords = false) {
    echo "<div class='card'>";
    echo "<h2>Fuel Credit Sales</h2>";
    
    $where = "WHERE (s.sale_type = 'fuel' OR s.sale_type = 'petroleum')";
    $params = [];
    $types = "";
    
    if (!$showAllRecords) {
        $where .= " AND s.sale_date BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
        $types .= "ss";
    }
    
    if ($customerId) {
        $where .= " AND cs.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    
    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM credit_sales cs
        JOIN sales s ON cs.sale_id = s.sale_id
        JOIN credit_customers cc ON cs.customer_id = cc.customer_id
        $where
    ";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $totalResult = $stmt->get_result();
        $totalRow = $totalResult->fetch_assoc();
        $totalRecords = $totalRow['total'];
        $stmt->close();
    } else {
        $totalResult = $conn->query($countQuery);
        $totalRow = $totalResult->fetch_assoc();
        $totalRecords = $totalRow['total'];
    }
    
    // Pagination setup
    $recordsPerPage = 15;
    $currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($currentPage - 1) * $recordsPerPage;
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Query for displaying records
    $query = "
        SELECT cs.*, cc.customer_name, s.invoice_number, s.sale_date, s.sale_type, 
               s.net_amount, CONCAT(staff.first_name, ' ', staff.last_name) as staff_name
        FROM credit_sales cs
        JOIN sales s ON cs.sale_id = s.sale_id
        JOIN credit_customers cc ON cs.customer_id = cc.customer_id
        LEFT JOIN staff ON s.staff_id = staff.staff_id
        $where
        ORDER BY s.sale_date DESC, s.sale_id DESC
        LIMIT $offset, $recordsPerPage
    ";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    
    if (!$result) {
        echo "<p>Error querying fuel credit sales: " . $conn->error . "</p>";
        echo "</div>";
        return;
    }
    
    if ($result->num_rows == 0) {
        echo "<p>No fuel credit sales found for the selected criteria.</p>";
        echo "</div>";
        return;
    }
    
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Invoice</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Remaining</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Staff</th>
            <th>Actions</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $statusClass = '';
        switch ($row['status']) {
            case 'paid':
                $statusClass = 'text-success';
                break;
            case 'partial':
                $statusClass = 'text-warning';
                break;
            case 'pending':
                $statusClass = $row['due_date'] < date('Y-m-d') ? 'text-danger' : 'text-primary';
                break;
        }
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['invoice_number']}</td>";
        echo "<td>" . date('Y-m-d', strtotime($row['sale_date'])) . "</td>";
        echo "<td>{$row['customer_name']}</td>";
        echo "<td>" . ucfirst($row['sale_type']) . "</td>";
        echo "<td>Rs. " . number_format($row['credit_amount'], 2) . "</td>";
        echo "<td>Rs. " . number_format($row['remaining_amount'], 2) . "</td>";
        echo "<td>" . date('Y-m-d', strtotime($row['due_date'])) . "</td>";
        echo "<td class='$statusClass'>" . ucfirst($row['status']) . "</td>";
        echo "<td>{$row['staff_name']}</td>";
        echo "<td class='action-buttons'>
                <a href='modules/credit_management/view_credit_customer.php?id={$row['customer_id']}' class='view'>View Customer</a>
                <a href='modules/credit_settlement/add_settlement.php?credit_id={$row['id']}' class='edit'>Add Payment</a>
              </td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Pagination
    if ($totalPages > 1) {
        echo "<div class='pagination'>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $activeClass = ($i == $currentPage) ? 'active' : '';
            echo "<a href='?page=$i" . 
                ($customerId ? "&customer_id=$customerId" : "") . 
                "&date_from=$dateFrom&date_to=$dateTo" . 
                ($showAllRecords ? "&show_all=1" : "") . 
                "' class='$activeClass'>$i</a>";
        }
        echo "</div>";
    }
    
    echo "</div>";
}

// Display customer transactions if a specific customer is selected
if ($customerId) {
    echo "<div class='card'>";
    echo "<h2>Customer Information</h2>";
    
    $stmt = $conn->prepare("SELECT * FROM credit_customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        echo "<table>";
        echo "<tr>
                <th>Customer Name</th>
                <th>Phone Number</th>
                <th>Current Balance</th>
                <th>Credit Limit</th>
                <th>Available Credit</th>
              </tr>";
        
        $availableCredit = $customer['credit_limit'] - $customer['current_balance'];
        $availableClass = $availableCredit > 0 ? 'text-success' : 'text-danger';
        
        echo "<tr>";
        echo "<td>{$customer['customer_name']}</td>";
        echo "<td>{$customer['phone_number']}</td>";
        echo "<td>Rs. " . number_format($customer['current_balance'], 2) . "</td>";
        echo "<td>Rs. " . number_format($customer['credit_limit'], 2) . "</td>";
        echo "<td class='$availableClass'>Rs. " . number_format($availableCredit, 2) . "</td>";
        echo "</tr>";
        echo "</table>";
    }
    
    echo "</div>";
    
    // Display recent transactions for this customer
    echo "<div class='card'>";
    echo "<h2>Recent Transactions</h2>";
    
    $query = "
        SELECT ct.*, 
               CASE WHEN s.invoice_number IS NOT NULL THEN s.invoice_number ELSE ct.reference_no END as invoice_ref
        FROM credit_transactions ct
        LEFT JOIN sales s ON ct.sale_id = s.sale_id
        WHERE ct.customer_id = ?
        ORDER BY ct.transaction_date DESC, ct.transaction_id DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Amount</th>
                <th>Details</th>
              </tr>";
        
        while ($row = $result->fetch_assoc()) {
            $typeClass = $row['transaction_type'] == 'payment' ? 'text-success' : 'text-danger';
            $amountPrefix = $row['transaction_type'] == 'payment' ? '-' : '+';
            
            echo "<tr>";
            echo "<td>" . date('Y-m-d', strtotime($row['transaction_date'])) . "</td>";
            echo "<td class='$typeClass'>" . ucfirst($row['transaction_type']) . "</td>";
            echo "<td>{$row['invoice_ref']}</td>";
            echo "<td>Rs. $amountPrefix" . number_format($row['amount'], 2) . "</td>";
            echo "<td>{$row['description']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No recent transactions found.</p>";
    }
    
    echo "</div>";
}

// Display all fuel credit sales
displayFuelCreditSales($conn, $dateFrom, $dateTo, $customerId, $showAllRecords);

// Create a section for daily fuel credit summary by customer
echo "<div class='card'>";
echo "<h2>Daily Fuel Credit Summary</h2>";

$query = "
    SELECT 
        DATE(s.sale_date) as sale_day,
        cs.customer_id,
        cc.customer_name,
        SUM(cs.credit_amount) as daily_total
    FROM credit_sales cs
    JOIN sales s ON cs.sale_id = s.sale_id
    JOIN credit_customers cc ON cs.customer_id = cc.customer_id
    WHERE (s.sale_type = 'fuel' OR s.sale_type = 'petroleum')
";

$params = [];
$types = "";

if (!$showAllRecords) {
    $query .= " AND s.sale_date BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    $types .= "ss";
}

if ($customerId) {
    $query .= " AND cs.customer_id = ?";
    $params[] = $customerId;
    $types .= "i";
}

$query .= "
    GROUP BY sale_day, cs.customer_id
    ORDER BY sale_day DESC, daily_total DESC
    LIMIT 30
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

if (!$result) {
    echo "<p>Error generating daily summary: " . $conn->error . "</p>";
} else if ($result->num_rows == 0) {
    echo "<p>No data available for daily summary.</p>";
} else {
    echo "<table>";
    echo "<tr>
            <th>Date</th>
            <th>Customer</th>
            <th>Total Credit Amount</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['sale_day'] . "</td>";
        echo "<td>{$row['customer_name']}</td>";
        echo "<td>Rs. " . number_format($row['daily_total'], 2) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "</div>";

// Footer
echo "</div>";

// Include footer
require_once 'includes/footer.php';
?>
