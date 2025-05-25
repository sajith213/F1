<?php
/**
 * POS Module Functions
 * 
 * This file contains helper functions for the Point of Sale module
 */
$currency_symbol = 'LKR'; // Default
// Include database connection if not already included
if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        if (!isset($GLOBALS['conn'])) {
            require_once __DIR__ . '/../../includes/db.php';
        }
        return $GLOBALS['conn'];
    }
}

/**
 * Get formatted price with currency symbol
 *
 * @param float $price The price to format
 * @return string Formatted price with currency symbol
 */
function formatPrice($price) {
    return CURRENCY_SYMBOL . number_format($price, 2);
}

/**
 * Generate a new invoice number
 *
 * @param string $prefix The prefix for the invoice number
 * @return string The generated invoice number
 */
function generateInvoiceNumber($prefix = 'INV-') {
    $conn = getDbConnection();
    $currentDate = date('Ymd');
    $nextInvoice = $prefix . $currentDate . '-0001';

    $stmt = $conn->prepare("
        SELECT invoice_number FROM sales
        WHERE invoice_number LIKE ? 
        ORDER BY invoice_number DESC LIMIT 1
    ");
    $likeParam = $prefix . $currentDate . '%';
    $stmt->bind_param("s", $likeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Extract last number and increment
        $lastInvoice = $row['invoice_number'];
        $lastNumber = (int)substr($lastInvoice, -4);
        $nextNumber = $lastNumber + 1;
        $nextInvoice = $prefix . $currentDate . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    $stmt->close();
    
    return $nextInvoice;
}

/**
 * Add a new product to the inventory
 *
 * @param array $productData Product data array
 * @return int|bool The product ID if successful, false otherwise
 */
function addProduct($productData) {
    $conn = getDbConnection();
    
    try {
        $conn->begin_transaction();
        
        // Extract product data
        $product_code = $productData['product_code'];
        $product_name = $productData['product_name'];
        $category_id = $productData['category_id'];
        $description = $productData['description'] ?? '';
        $unit = $productData['unit'];
        $purchase_price = $productData['purchase_price'];
        $selling_price = $productData['selling_price'];
        $current_stock = $productData['current_stock'] ?? 0;
        $reorder_level = $productData['reorder_level'];
        $barcode = $productData['barcode'] ?? '';
        $status = $productData['status'] ?? 'active';
        
        // Insert product
        $stmt = $conn->prepare("
            INSERT INTO products (
                product_code, product_name, category_id, description, unit,
                purchase_price, selling_price, current_stock, reorder_level,
                barcode, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssissddiiss",
            $product_code, $product_name, $category_id, $description, $unit,
            $purchase_price, $selling_price, $current_stock, $reorder_level,
            $barcode, $status
        );
        
        $stmt->execute();
        $product_id = $conn->insert_id;
        $stmt->close();
        
        // If adding with initial stock, create inventory transaction
        if ($current_stock > 0) {
            // Record inventory transaction
            $stmt = $conn->prepare("
                INSERT INTO inventory_transactions (
                    product_id, transaction_type, reference_id, previous_quantity,
                    change_quantity, new_quantity, transaction_date, conducted_by
                ) VALUES (?, 'purchase', NULL, 0, ?, ?, NOW(), ?)
            ");
            
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param("iiii", $product_id, $current_stock, $current_stock, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        return $product_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Error adding product: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing product
 *
 * @param int $product_id Product ID
 * @param array $productData Updated product data
 * @return bool True if successful, false otherwise
 */
function updateProduct($product_id, $productData) {
    $conn = getDbConnection();
    
    try {
        // Extract product data
        $product_name = $productData['product_name'];
        $category_id = $productData['category_id'];
        $description = $productData['description'] ?? '';
        $unit = $productData['unit'];
        $purchase_price = $productData['purchase_price'];
        $selling_price = $productData['selling_price'];
        $reorder_level = $productData['reorder_level'];
        $barcode = $productData['barcode'] ?? '';
        $status = $productData['status'] ?? 'active';
        
        // Update product
        $stmt = $conn->prepare("
            UPDATE products SET 
                product_name = ?, 
                category_id = ?, 
                description = ?, 
                unit = ?,
                purchase_price = ?, 
                selling_price = ?, 
                reorder_level = ?,
                barcode = ?, 
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE product_id = ?
        ");
        
        $stmt->bind_param(
            "sissddissi",
            $product_name, $category_id, $description, $unit,
            $purchase_price, $selling_price, $reorder_level,
            $barcode, $status, $product_id
        );
        
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows > 0;
        
    } catch (Exception $e) {
        error_log('Error updating product: ' . $e->getMessage());
        return false;
    }
}
function getCreditCustomers($conn) {
    $customers = [];
    
    // Check if connection is valid
    if (!$conn) {
        error_log("Database connection not provided to getCreditCustomers()");
        return $customers; // Return empty array instead of causing an error
    }
    
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'sale' THEN t.amount ELSE 0 END), 0) as total_sales,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) as total_payments
        FROM credit_customers c
        LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
        WHERE c.status = 'active'
        GROUP BY c.customer_id
        ORDER BY c.customer_name ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        $stmt->close();
    }
    
    return $customers;
}

/**
 * Get product by ID
 *
 * @param int $product_id Product ID
 * @return array|null Product data or null if not found
 */
function getProductById($product_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT p.*, c.category_name 
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.category_id
        WHERE p.product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $product = $result->fetch_assoc();
    $stmt->close();
    
    return $product;
}

/**
 * Update product stock quantity
 *
 * @param int $product_id Product ID
 * @param int $quantity_change Amount to add (positive) or subtract (negative)
 * @param string $transaction_type Type of transaction ('purchase', 'sale', 'adjustment', etc.)
 * @param int|null $reference_id Reference ID (e.g., sale_id, po_id)
 * @return bool True if successful, false otherwise
 */
function updateProductStock($product_id, $quantity_change, $transaction_type, $reference_id = null) {
    $conn = getDbConnection();
    
    try {
        $conn->begin_transaction();
        
        // Get current stock
        $stmt = $conn->prepare("SELECT current_stock FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Product not found");
        }
        
        $row = $result->fetch_assoc();
        $current_stock = $row['current_stock'];
        $stmt->close();
        
        // Calculate new stock level
        $new_stock = $current_stock + $quantity_change;
        
        // Update product stock
        $stmt = $conn->prepare("UPDATE products SET current_stock = ?, updated_at = CURRENT_TIMESTAMP WHERE product_id = ?");
        $stmt->bind_param("ii", $new_stock, $product_id);
        $stmt->execute();
        $stmt->close();
        
        // Add inventory transaction record
        $stmt = $conn->prepare("
            INSERT INTO inventory_transactions (
                product_id, transaction_type, reference_id, previous_quantity,
                change_quantity, new_quantity, transaction_date, conducted_by
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param(
            "isiiddi",
            $product_id, $transaction_type, $reference_id, $current_stock,
            $quantity_change, $new_stock, $user_id
        );
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Error updating product stock: ' . $e->getMessage());
        return false;
    }
}
/**
 * Process a sale
 *
 * @param array $saleData Sale data array
 * @return int|bool The sale ID if successful, false otherwise
 */
function processSale($saleData) {
    $conn = getDbConnection();
    
    try {
        $conn->begin_transaction();
        
        // Extract sale data
        $invoice_number = $saleData['invoice_number'];
        $sale_date = $saleData['sale_date'] ?? date('Y-m-d H:i:s');
        $customer_name = $saleData['customer_name'] ?? null;
        $customer_phone = $saleData['customer_phone'] ?? null;
        $sale_type = $saleData['sale_type'];
        $staff_id = $saleData['staff_id'];
        $total_amount = $saleData['total_amount'];
        $discount_amount = $saleData['discount_amount'] ?? 0;
        $tax_amount = $saleData['tax_amount'] ?? 0;
        $net_amount = $saleData['net_amount'];
        $payment_method = $saleData['payment_method'];
        $payment_status = $saleData['payment_status'] ?? 'paid';
        $notes = $saleData['notes'] ?? null;
        $items = $saleData['items'] ?? [];
        
        // Insert sale record
        $stmt = $conn->prepare("
            INSERT INTO sales (
                invoice_number, sale_date, customer_name, customer_phone, sale_type,
                staff_id, total_amount, discount_amount, tax_amount, net_amount,
                payment_method, payment_status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sssssiddddsss",
            $invoice_number, $sale_date, $customer_name, $customer_phone, $sale_type,
            $staff_id, $total_amount, $discount_amount, $tax_amount, $net_amount,
            $payment_method, $payment_status, $notes
        );
        
        $stmt->execute();
        $sale_id = $conn->insert_id;
        $stmt->close();
        
        // Process sale items
        foreach ($items as $item) {
            $item_type = $item['type'];
            $product_id = ($item_type === 'product') ? $item['id'] : null;
            $nozzle_id = ($item_type === 'fuel') ? $item['id'] : null;
            $quantity = $item['quantity'];
            $unit_price = $item['price'];
            $discount_percentage = $item['discount_percentage'] ?? 0;
            $discount_amount = $item['discount_amount'] ?? 0;
            
            // Insert sale item
            $stmt = $conn->prepare("
                INSERT INTO sale_items (
                    sale_id, item_type, product_id, nozzle_id, quantity,
                    unit_price, discount_percentage, discount_amount
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "isiidddd",
                $sale_id, $item_type, $product_id, $nozzle_id, $quantity,
                $unit_price, $discount_percentage, $discount_amount
            );
            
            $stmt->execute();
            $stmt->close();
            
            // Update inventory for products
            if ($item_type === 'product' && $product_id) {
                updateProductStock($product_id, -$quantity, 'sale', $sale_id);
            }
        }
        
        $conn->commit();
        return $sale_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Error processing sale: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get sale by ID with items
 *
 * @param int $sale_id Sale ID
 * @return array|null Sale data with items or null if not found
 */
function getSaleWithItems($sale_id) {
    $conn = getDbConnection();
    
    // Get sale details
    $stmt = $conn->prepare("
        SELECT s.*, 
               staff.first_name, staff.last_name
        FROM sales s
        LEFT JOIN staff ON s.staff_id = staff.staff_id
        WHERE s.sale_id = ?
    ");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $sale = $result->fetch_assoc();
    $stmt->close();
    
    // Get sale items
    $stmt = $conn->prepare("
        SELECT si.*, 
               p.product_name, p.product_code,
               ft.fuel_name,
               pn.nozzle_number,
               pu.pump_name
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.product_id
        LEFT JOIN pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
        LEFT JOIN pumps pu ON pn.pump_id = pu.pump_id
        LEFT JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
        WHERE si.sale_id = ?
        ORDER BY si.item_id
    ");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sale['items'] = [];
    while ($row = $result->fetch_assoc()) {
        $sale['items'][] = $row;
    }
    $stmt->close();
    
    return $sale;
}
/**
 * Get daily sales summary
 *
 * @param string $date Date in Y-m-d format
 * @return array Sales summary data
 */
function getDailySalesSummary($date = null) {
    $conn = getDbConnection();
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $summary = [
        'total_sales' => 0,
        'total_amount' => 0,
        'product_sales' => 0,
        'product_amount' => 0,
        'fuel_sales' => 0,
        'fuel_amount' => 0,
        'mixed_sales' => 0,
        'mixed_amount' => 0,
        'payment_methods' => [],
        'hourly_distribution' => []
    ];
    
    // Get sales totals by type
    $stmt = $conn->prepare("
        SELECT sale_type, COUNT(*) as count, SUM(net_amount) as total
        FROM sales
        WHERE DATE(sale_date) = ? AND payment_status != 'cancelled'
        GROUP BY sale_type
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $type = $row['sale_type'];
        $count = $row['count'];
        $total = $row['total'];
        
        $summary['total_sales'] += $count;
        $summary['total_amount'] += $total;
        
        if ($type === 'product') {
            $summary['product_sales'] = $count;
            $summary['product_amount'] = $total;
        } elseif ($type === 'fuel') {
            $summary['fuel_sales'] = $count;
            $summary['fuel_amount'] = $total;
        } elseif ($type === 'mixed') {
            $summary['mixed_sales'] = $count;
            $summary['mixed_amount'] = $total;
        }
    }
    $stmt->close();
    
    // Get payment method distribution
    $stmt = $conn->prepare("
        SELECT payment_method, COUNT(*) as count, SUM(net_amount) as total
        FROM sales
        WHERE DATE(sale_date) = ? AND payment_status != 'cancelled'
        GROUP BY payment_method
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $summary['payment_methods'][] = [
            'method' => $row['payment_method'],
            'count' => $row['count'],
            'total' => $row['total']
        ];
    }
    $stmt->close();
    
    // Get hourly distribution
    $stmt = $conn->prepare("
        SELECT HOUR(sale_date) as hour, COUNT(*) as count, SUM(net_amount) as total
        FROM sales
        WHERE DATE(sale_date) = ? AND payment_status != 'cancelled'
        GROUP BY HOUR(sale_date)
        ORDER BY hour
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize all hours with zero
    for ($i = 0; $i < 24; $i++) {
        $summary['hourly_distribution'][$i] = [
            'hour' => $i,
            'count' => 0,
            'total' => 0
        ];
    }
    
    while ($row = $result->fetch_assoc()) {
        $hour = $row['hour'];
        $summary['hourly_distribution'][$hour] = [
            'hour' => $hour,
            'count' => $row['count'],
            'total' => $row['total']
        ];
    }
    $stmt->close();
    
    return $summary;
}