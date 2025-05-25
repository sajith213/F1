<?php
/**
 * POS API Endpoint
 * 
 * This file handles API requests for the Point of Sale module
 */

// Start output buffering to capture any unexpected output
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to output
ini_set('log_errors', 1);
ini_set('error_log', '../logs/pos_api_errors.log'); // Adjust path as needed

// Set JSON content type early
header('Content-Type: application/json');

// Ensure we return JSON even if errors occur
function shutdown_handler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Clear any output that might have been generated
        ob_clean();
        
        // Log the error
        error_log("Fatal error in POS API: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Send proper JSON error response
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
    ob_end_flush();
}
register_shutdown_function('shutdown_handler');

// Custom exception handler
function exception_handler($exception) {
    // Clear any output that might have been generated
    ob_clean();
    
    // Log the exception
    error_log("Exception in POS API: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Send proper JSON error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Exception: ' . $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine()
    ]);
    exit;
}
set_exception_handler('exception_handler');

// Custom error handler
function error_handler($errno, $errstr, $errfile, $errline) {
    // For non-fatal errors, log but don't interrupt execution
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return false;
    }
    
    // Log the error
    error_log("Error in POS API: [$errno] $errstr in $errfile on line $errline");
    
    // Only terminate for serious errors
    if (in_array($errno, [E_ERROR, E_USER_ERROR])) {
        ob_clean();
        echo json_encode([
            'status' => 'error',
            'message' => "Error: $errstr",
            'file' => basename($errfile),
            'line' => $errline
        ]);
        exit;
    }
    
    // Return true to indicate the error has been handled
    return true;
}
set_error_handler('error_handler');

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Include required files - use require_once to prevent redeclaration errors
try {
    require_once '../includes/db.php';
    
    // Important: Use require_once instead of require to prevent function redeclaration
    require_once '../modules/pos/functions.php';
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load required files: ' . $e->getMessage()
    ]);
    exit;
}

// Get request action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Process API requests based on action
try {
    switch ($action) {
        case 'getProducts':
            getProducts();
            break;
            
        case 'getProduct':
            getProduct();
            break;
            
        case 'getFuelNozzles':
            getFuelNozzles();
            break;
            
        case 'processSale':
            handleSale();
            break;
            
        case 'getSaleDetails':
            getSaleDetails();
            break;
            
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action specified: ' . $action
            ]);
            break;
    }
} catch (Exception $e) {
    // This will be caught by our exception handler
    throw $e;
}

/**
 * Get products with filtering options
 */
function getProducts() {
    global $conn;
    
    try {
        // Get filter parameters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        // Base query for products
        $query = "
            SELECT p.*, c.category_name 
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
        ";
        
        $params = [];
        $types = "";
        
        // Apply search filter
        if (!empty($search)) {
            $search = "%{$search}%";
            $query .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= "sss";
        }
        
        // Apply category filter
        if ($category > 0) {
            $query .= " AND p.category_id = ?";
            $params[] = $category;
            $types .= "i";
        }
        
        // Add sorting and limit
        $query .= " ORDER BY p.product_name ASC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        // Execute query
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $executed = $stmt->execute();
        if (!$executed) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'products' => $products
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get products: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get product details by ID
 */
function getProduct() {
    global $conn;
    
    try {
        $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($product_id <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid product ID'
            ]);
            return;
        }
        
        // Check if getProductById exists in functions.php
        if (function_exists('getProductById')) {
            $product = getProductById($product_id);
            
            if (!$product) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found'
                ]);
                return;
            }
            
            echo json_encode([
                'status' => 'success',
                'product' => $product
            ]);
        } else {
            // Fallback if function doesn't exist
            $stmt = $conn->prepare("
                SELECT p.*, c.category_name 
                FROM products p
                LEFT JOIN product_categories c ON p.category_id = c.category_id
                WHERE p.product_id = ? AND p.status = 'active'
            ");
            
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Product not found'
                ]);
                return;
            }
            
            $product = $result->fetch_assoc();
            $stmt->close();
            
            echo json_encode([
                'status' => 'success',
                'product' => $product
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get product: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get fuel nozzles with current prices
 */
function getFuelNozzles() {
    global $conn;
    
    try {
        $query = "
            SELECT n.nozzle_id, n.nozzle_number, n.fuel_type_id, n.status,
                p.pump_id, p.pump_name,
                f.fuel_name, fp.selling_price
            FROM pump_nozzles n
            JOIN pumps p ON n.pump_id = p.pump_id
            JOIN fuel_types f ON n.fuel_type_id = f.fuel_type_id
            LEFT JOIN (
                SELECT fuel_type_id, selling_price 
                FROM fuel_prices 
                WHERE status = 'active' 
                GROUP BY fuel_type_id 
                HAVING effective_date = MAX(effective_date)
            ) fp ON f.fuel_type_id = fp.fuel_type_id
            WHERE n.status = 'active' AND p.status = 'active'
            ORDER BY p.pump_name, n.nozzle_number
        ";
        
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $nozzles = [];
        while ($row = $result->fetch_assoc()) {
            $nozzles[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'nozzles' => $nozzles
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get fuel nozzles: ' . $e->getMessage()
        ]);
    }
}

/**
 * Send a standardized JSON response
 */
function sendResponse($status, $message, $data = []) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Generate a unique invoice number
 * This function uses row locking to prevent race conditions
 */
function generateUniqueInvoiceNumber($conn) {
    $invoicePrefix = 'INV-';
    $currentDate = date('Ymd');
    
    // Begin a transaction for row locking
    $conn->begin_transaction();
    
    try {
        // Use FOR UPDATE to lock the rows during query to prevent race conditions
        $stmt = $conn->prepare("
            SELECT invoice_number FROM sales
            WHERE invoice_number LIKE ? 
            ORDER BY invoice_number DESC LIMIT 1
            FOR UPDATE
        ");
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $likeParam = $invoicePrefix . $currentDate . '%';
        $stmt->bind_param("s", $likeParam);
        
        $executed = $stmt->execute();
        if (!$executed) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $nextInvoice = $invoicePrefix . $currentDate . '-0001';
        if ($row = $result->fetch_assoc()) {
            // Extract last number and increment
            $lastInvoice = $row['invoice_number'];
            $lastNumber = (int)substr($lastInvoice, -4);
            $nextNumber = $lastNumber + 1;
            $nextInvoice = $invoicePrefix . $currentDate . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        return $nextInvoice;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error generating invoice number: " . $e->getMessage());
        throw $e; // Re-throw to be caught by the caller
    }
}

/**
 * Process a new sale
 */
function handleSale() {
    global $conn;
    
    // Check for POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse('error', 'Invalid request method');
        return;
    }
    
    // Get and validate JSON input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        sendResponse('error', 'No input data received');
        return;
    }
    
    // Decode JSON data with error checking
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse('error', 'Invalid JSON data: ' . json_last_error_msg());
        return;
    }
    
    // Log the received data for debugging
    error_log("Received sale data: " . json_encode($data));
    
    // Validate required sale data
    $requiredFields = ['items', 'total_amount', 'tax_amount', 'net_amount', 'payment_method'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_array($data[$field]) && empty($data[$field]))) {
            sendResponse('error', "Missing required field: {$field}");
            return;
        }
    }
    
    // Validate items array
    if (!is_array($data['items']) || empty($data['items'])) {
        sendResponse('error', 'Sale must contain at least one item');
        return;
    }
    
    // Validate numeric fields
    $numericFields = ['total_amount', 'tax_amount', 'net_amount'];
    foreach ($numericFields as $field) {
        if (!is_numeric($data[$field])) {
            sendResponse('error', "Field {$field} must be numeric");
            return;
        }
    }
    
    // Determine sale type
    $hasProducts = false;
    $hasFuel = false;
    
    foreach ($data['items'] as $item) {
        if (!isset($item['type']) || !isset($item['id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
            sendResponse('error', 'Each item must have type, id, quantity, and unit_price');
            return;
        }
        
        if ($item['type'] === 'product') {
            $hasProducts = true;
        } elseif ($item['type'] === 'fuel') {
            $hasFuel = true;
        } else {
            sendResponse('error', "Invalid item type: {$item['type']}");
            return;
        }
    }
    
    if ($hasProducts && $hasFuel) {
        $data['sale_type'] = 'mixed';
    } elseif ($hasProducts) {
        $data['sale_type'] = 'product';
    } elseif ($hasFuel) {
        $data['sale_type'] = 'fuel';
    } else {
        sendResponse('error', 'Invalid sale items');
        return;
    }
    
    // Validate credit sale data if applicable
    if (isset($data['is_credit']) && $data['is_credit']) {
        if (!isset($data['credit_customer_id']) || empty($data['credit_customer_id'])) {
            sendResponse('error', 'Credit sales require a customer');
            return;
        }
        
        if (!isset($data['due_date']) || empty($data['due_date'])) {
            sendResponse('error', 'Credit sales require a due date');
            return;
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
            sendResponse('error', 'Due date must be in YYYY-MM-DD format');
            return;
        }
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Generate a unique invoice number server-side, regardless of what the client sent
        $data['invoice_number'] = generateUniqueInvoiceNumber($conn);
        
        // Call the main sale processing function
        $saleId = createSaleRecord($conn, $data);
        
        if (!$saleId) {
            throw new Exception('Failed to process sale - no sale ID was generated');
        }
        
        // Set up the response
        $receipt_url = "../pos/receipt.php?id={$saleId}";
        $response = [
            'status' => 'success',
            'sale_id' => $saleId,
            'invoice_number' => $data['invoice_number'],
            'receipt_url' => $receipt_url,
            'message' => 'Sale completed successfully'
        ];
        
        // Handle credit sale processing if applicable
        if (isset($data['is_credit']) && $data['is_credit'] && 
            isset($data['credit_customer_id']) && !empty($data['credit_customer_id'])) {
            
            // Include credit sales functions if needed
            require_once '../modules/pos/credit_sales_functions.php';
            
            // Process the credit sale
            $userId = $_SESSION['user_id'];
            $creditResult = processCreditSale(
                $conn, 
                $data['credit_customer_id'], 
                $saleId,  // This must be a valid sale_id
                $data['net_amount'], 
                $data['due_date'],
                $userId
            );
            
            if (!$creditResult) {
                throw new Exception('Failed to process credit sale');
            }
            
            // Add credit-specific information to the response
            $response['is_credit'] = true;
            $response['credit_customer_id'] = $data['credit_customer_id'];
            $response['due_date'] = $data['due_date'];
            $receipt_url = "../pos/credit_receipt.php?id={$saleId}";
            $response['receipt_url'] = $receipt_url;
        }
        
        // If we got this far, commit the transaction
        $conn->commit();
        
        // Return the response
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log the error
        error_log("Sale processing error: " . $e->getMessage());
        
        // Send error response
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to process sale: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create sale record function
 */
function createSaleRecord($conn, $data) {
    error_log("Starting sale record creation with data: " . json_encode($data));
    
    try {
        // Insert sale record
        $stmt = $conn->prepare("
            INSERT INTO sales (
                invoice_number, sale_date, customer_name, customer_phone,
                sale_type, staff_id, total_amount, discount_amount,
                tax_amount, net_amount, payment_method, payment_status,
                notes
            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $paymentStatus = isset($data['is_credit']) && $data['is_credit'] ? 'pending' : 'paid';
        $customerName = $data['customer_name'] ?? null;
        $customerPhone = $data['customer_phone'] ?? null;
        $staffId = $data['staff_id'] ?? null;
        $discountAmount = $data['discount_amount'] ?? 0;
        $notes = $data['notes'] ?? '';
        
        $stmt->bind_param(
            "ssssiidddsss",
            $data['invoice_number'],
            $customerName,
            $customerPhone,
            $data['sale_type'],
            $staffId,
            $data['total_amount'],
            $discountAmount,
            $data['tax_amount'],
            $data['net_amount'],
            $data['payment_method'],
            $paymentStatus,
            $notes
        );
        
        $stmt->execute();
        $saleId = $conn->insert_id;
        $stmt->close();
        
        if (!$saleId) {
            error_log("No sale ID generated after INSERT");
            throw new Exception('Failed to create sale record - no sale ID was generated');
        }
        
        error_log("Sale record created with ID: " . $saleId);
        
        // Process each item in the sale
        foreach ($data['items'] as $item) {
            error_log("Processing item: " . json_encode($item));
            
            if ($item['type'] === 'product') {
                // Insert product item
                $stmt = $conn->prepare("
                    INSERT INTO sale_items (
                        sale_id, item_type, product_id, quantity, unit_price,
                        discount_percentage, discount_amount
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $itemType = 'product';
                $discountPercentage = $item['discount_percentage'] ?? 0;
                $discountAmount = $item['discount_amount'] ?? 0;
                
                $stmt->bind_param(
                    "isidddd",
                    $saleId,
                    $itemType,
                    $item['id'], // product_id
                    $item['quantity'],
                    $item['unit_price'],
                    $discountPercentage,
                    $discountAmount
                );
                
                $stmt->execute();
                $stmt->close();
                
                // Update product inventory
                $stmt = $conn->prepare("
                    UPDATE products 
                    SET current_stock = current_stock - ? 
                    WHERE product_id = ?
                ");
                
                $stmt->bind_param("di", $item['quantity'], $item['id']);
                $stmt->execute();
                $stmt->close();
                
            } elseif ($item['type'] === 'fuel') {
                // Insert fuel item into sale_items table
                $stmt = $conn->prepare("
                    INSERT INTO sale_items (
                        sale_id, item_type, nozzle_id, quantity, unit_price,
                        discount_percentage, discount_amount
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $itemType = 'fuel';
                $discountPercentage = $item['discount_percentage'] ?? 0;
                $discountAmount = $item['discount_amount'] ?? 0;
                
                $stmt->bind_param(
                    "isidddd",
                    $saleId,
                    $itemType,
                    $item['id'], // nozzle_id
                    $item['quantity'], // liters
                    $item['unit_price'],
                    $discountPercentage,
                    $discountAmount
                );
                
                $stmt->execute();
                $stmt->close();
            }
        }
        
        error_log("Successfully created sale with ID: " . $saleId);
        return $saleId;
    } catch (Exception $e) {
        error_log("Error creating sale record: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get sale details by ID
 */
function getSaleDetails() {
    global $conn;
    
    try {
        $sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($sale_id <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid sale ID'
            ]);
            return;
        }
        
        // Check if getSaleWithItems exists in functions.php
        if (function_exists('getSaleWithItems')) {
            $sale = getSaleWithItems($sale_id);
            
            if (!$sale) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Sale not found'
                ]);
                return;
            }
            
            echo json_encode([
                'status' => 'success',
                'sale' => $sale
            ]);
        } else {
            // Fallback implementation if the function doesn't exist
            $sale = [];
            
            // Get sale header
            $stmt = $conn->prepare("
                SELECT * FROM sales WHERE sale_id = ?
            ");
            $stmt->bind_param("i", $sale_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Sale not found'
                ]);
                return;
            }
            
            $sale = $result->fetch_assoc();
            $stmt->close();
            
            // Get sale items
            $stmt = $conn->prepare("
                SELECT * FROM sale_items WHERE sale_id = ?
            ");
            $stmt->bind_param("i", $sale_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = [];
            
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            $stmt->close();
            
            $sale['items'] = $items;
            
            echo json_encode([
                'status' => 'success',
                'sale' => $sale
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get sale details: ' . $e->getMessage()
        ]);
    }
}