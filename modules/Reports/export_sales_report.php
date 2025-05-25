<?php
/**
 * Export Sales Report
 * 
 * Exports the sales report data to CSV format
 */
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission to view reports
if (!has_permission('view_reports')) {
    set_flash_message('error', 'You do not have permission to export reports');
    header('Location: ../../index.php');
    exit;
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$fuel_type_id = isset($_GET['fuel_type_id']) ? intval($_GET['fuel_type_id']) : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$sale_type = isset($_GET['sale_type']) ? $_GET['sale_type'] : '';

// Sanitize inputs
$start_date = mysqli_real_escape_string($conn, $start_date);
$end_date = mysqli_real_escape_string($conn, $end_date);
$payment_method = mysqli_real_escape_string($conn, $payment_method);
$sale_type = mysqli_real_escape_string($conn, $sale_type);

// Prepare the SQL query conditions
$conditions = ["s.sale_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'"];
if ($fuel_type_id > 0) {
    $conditions[] = "pn.fuel_type_id = $fuel_type_id";
}
if (!empty($payment_method)) {
    $conditions[] = "s.payment_method = '$payment_method'";
}
// Add sale type filter condition
if ($sale_type === 'fuel') {
    $conditions[] = "si.item_type = 'fuel'";
} elseif ($sale_type === 'product') {
    $conditions[] = "si.item_type = 'product'";
}

// Build the WHERE clause
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get sales data for export
$query = "
    SELECT 
        s.invoice_number,
        s.sale_date,
        s.customer_name,
        ft.fuel_name,
        si.item_type,
        si.quantity,
        si.unit_price,
        si.total_price,
        s.total_amount,
        s.payment_method,
        s.payment_status,
        CONCAT(st.first_name, ' ', st.last_name) as staff_name
    FROM 
        sales s
    JOIN 
        sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN 
        pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
    LEFT JOIN 
        fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
    LEFT JOIN 
        staff st ON s.staff_id = st.staff_id
    $where_clause
    ORDER BY 
        s.sale_date DESC
";

$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');

// Create a file handle for PHP output
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Invoice #', 
    'Date & Time', 
    'Customer', 
    'Item Type',
    'Fuel Type', 
    'Quantity (L)', 
    'Unit Price', 
    'Total Price', 
    'Total Invoice Amount', 
    'Payment Method', 
    'Payment Status', 
    'Staff'
]);

// Add data rows
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Prepare the staff name
        $staff_name = $row['staff_name'];
        if (empty($staff_name)) {
            $staff_name = 'N/A';
        }
        
        // Prepare the customer name
        $customer_name = !empty($row['customer_name']) ? $row['customer_name'] : 'Walk-in Customer';
        
        fputcsv($output, [
            $row['invoice_number'] ?? 'N/A',
            $row['sale_date'],
            $customer_name,
            ucfirst($row['item_type'] ?? 'Unknown'),
            $row['fuel_name'] ?? 'N/A',
            $row['quantity'],
            $row['unit_price'],
            $row['total_price'],
            $row['total_amount'],
            ucfirst(str_replace('_', ' ', $row['payment_method'] ?? 'Unknown')),
            ucfirst($row['payment_status'] ?? 'Unknown'),
            $staff_name
        ]);
    }
}

// Close the file handle
fclose($output);
exit;