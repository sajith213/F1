<?php
ob_start();
/**
 * POS Module - Receipt Generation
 * This file generates a receipt for a completed sale with a clean, professional look
 */
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Determine the project root path based on the current file's location.
// __DIR__ is the directory of the current file: /home/calilpkt/koralaimashed.xyz/modules/pos/
// dirname(__DIR__, 2) goes up two levels from the script's directory to: /home/calilpkt/koralaimashed.xyz/
$projectRootPath = dirname(__DIR__, 2);

// Include dependencies using the determined project root path
include_once $projectRootPath . '/includes/header.php'; // Should resolve to /home/calilpkt/koralaimashed.xyz/includes/header.php
require_once $projectRootPath . '/includes/db.php';    // Should resolve to /home/calilpkt/koralaimashed.xyz/includes/db.php
require_once __DIR__ . '/functions.php';              // This correctly points to functions.php in the same directory as receipt.php

// --- Configuration & Initialization ---
// ... rest of your PHP script ... 

// --- Configuration & Initialization ---
$page_title = "Sale Receipt";
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sale = null;
$sale_items = [];
$error_message = '';

// Settings defaults (will be overridden from DB)
$company_name = 'Your Company Name';
$company_address = '';
$company_phone = '';
$company_email = '';
$receipt_footer = 'Thank You!';
$currency_symbol = 'Rs.'; // Default for LKR

// --- Fetch System Settings ---
try {
    $settings_query = "SELECT setting_name, setting_value FROM system_settings 
                      WHERE setting_name IN ('company_name', 'company_address', 'company_phone', 
                                             'company_email', 'receipt_footer', 'currency_symbol')";
    $result = $conn->query($settings_query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            ${$row['setting_name']} = $row['setting_value']; 
        }
    }
} catch (Exception $e) {
    $error_message = "Database error fetching settings: " . $e->getMessage();
    error_log("Database error fetching settings: " . $e->getMessage());
}

// --- Fetch Sale Data ---
if ($sale_id > 0) {
    try {
        // Fetch main sale details
        $stmt_sale = $conn->prepare("
            SELECT s.*, 
                   st.first_name, st.last_name 
            FROM sales s
            LEFT JOIN staff st ON s.staff_id = st.staff_id 
            WHERE s.sale_id = ?
        ");
        
        if (!$stmt_sale) {
            throw new Exception("Error preparing sale statement: " . $conn->error);
        }
        
        $stmt_sale->bind_param("i", $sale_id);
        $stmt_sale->execute();
        $result_sale = $stmt_sale->get_result();
            
        if ($result_sale->num_rows === 0) {
            $error_message = "Sale with ID {$sale_id} not found.";
        } else {
            $sale = $result_sale->fetch_assoc();
                
            // Fetch sale items
            $stmt_items = $conn->prepare("
                SELECT si.*, 
                       p.product_name, p.product_code,
                       ft.fuel_name,
                       pn.nozzle_number,
                       pu.pump_name
                FROM sale_items si
                LEFT JOIN products p ON si.product_id = p.product_id AND si.item_type = 'product'
                LEFT JOIN pump_nozzles pn ON si.nozzle_id = pn.nozzle_id AND si.item_type = 'fuel'
                LEFT JOIN pumps pu ON pn.pump_id = pu.pump_id
                LEFT JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
                WHERE si.sale_id = ?
                ORDER BY si.item_id ASC
            ");

            $stmt_items->bind_param("i", $sale_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
                    
            while ($row = $result_items->fetch_assoc()) {
                if (!isset($row['net_amount'])) {
                    $row['net_amount'] = ($row['unit_price'] * $row['quantity']) - ($row['discount_amount'] ?? 0);
                }
                $sale_items[] = $row;
            }
            $stmt_items->close();
        }
        $stmt_sale->close();
        
    } catch (Exception $e) {
        $error_message = "Error retrieving sale data: " . $e->getMessage();
        error_log("Receipt error: " . $e->getMessage());
    }
} else {
    $error_message = "No valid Sale ID provided.";
}

// Debug information for troubleshooting
if ($isWebHosting) {
    error_log("Web hosting environment detected");
    error_log("Document root: " . $_SERVER['DOCUMENT_ROOT']);
    error_log("Base path: " . $basePath);
    error_log("Sale ID: " . $sale_id);
    error_log("Error message: " . $error_message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($sale['invoice_number'] ?? 'Not Found') ?></title>
    <style>
        /* Reset and base styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            background: #f8f8f8;
        }
        
        /* Container for screen viewing */
        .page-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 20px;
        }
        
        /* Navigation bar */
        .nav-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background: #fff;
            padding: 10px 15px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .nav-title {
            font-size: 18px;
            font-weight: bold;
        }
        
        .nav-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: #1a56db;
            color: white;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        /* Receipt container for screen viewing */
        .receipt-wrapper {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        /* Receipt styling shared between screen and print */
        .receipt {
            width: 100%;
            font-size: 10pt;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 9pt;
            color: #444;
            margin-bottom: 3px;
        }
        
        .receipt-title {
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
        }
        
        .receipt-info {
            font-size: 9pt;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .divider {
            border-top: 1px dashed #ccc;
            margin: 10px 0;
        }
        
        .customer-info {
            margin: 8px 0;
            font-size: 9pt;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .items-table th {
            text-align: left;
            padding: 5px 3px;
            border-bottom: 1px solid #ddd;
            font-size: 9pt;
        }
        
        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }
        
        .items-table th:nth-child(2),
        .items-table td:nth-child(2) {
            text-align: center;
        }
        
        .items-table th:nth-child(3),
        .items-table td:nth-child(3) {
            text-align: right;
        }
        
        .items-table td {
            padding: 4px 3px;
            border-bottom: 1px dotted #eee;
            font-size: 9pt;
        }
        
        .summary-table {
            width: 100%;
            margin: 10px 0;
            font-size: 9pt;
        }
        
        .summary-table td {
            padding: 2px 0;
        }
        
        .summary-table td:last-child {
            text-align: right;
        }
        
        .summary-total {
            font-weight: bold;
            font-size: 11pt;
            padding-top: 4px !important;
            border-top: 1px solid #000;
        }
        
        .payment-info {
            margin-top: 10px;
            font-size: 9pt;
        }
        
        .payment-info table {
            width: 100%;
        }
        
        .payment-info td {
            padding: 2px 0;
        }
        
        .payment-info td:last-child {
            text-align: right;
        }
        
        .receipt-footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9pt;
        }
        
        .footer-message {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .footer-info {
            font-size: 8pt;
            color: #666;
        }
        
        /* Print-specific styles */
        @media print {
            /* Hide everything except receipt */
            body * {
                visibility: hidden;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white; 
            }
            
            .receipt-wrapper, .receipt-wrapper * {
                visibility: visible;
            }
            
            .receipt-wrapper {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm; /* Standard thermal receipt width */
                max-width: 100%;
                padding: 5mm;
                margin: 0;
                border: none;
                box-shadow: none;
                border-radius: 0;
                background: white;
            }
            
            .nav-bar, .btn, .no-print {
                display: none !important;
            }
            
            /* Paper-specific adjustments */
            .divider {
                border-top: 1px dashed #999;
            }
            
            .company-name {
                font-size: 12pt; 
            }
            
            .receipt-title {
                font-size: 11pt;
            }
            
            /* Fix for printed page breaks */
            .items-table, .summary-table, .payment-info {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Navigation bar - only visible on screen -->
        <div class="nav-bar no-print">
            <div class="nav-title">Sale Receipt</div>
            <div class="nav-actions">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to POS
                </a>
                <?php if (empty($error_message) && $sale): ?>
                <button onclick="printReceipt()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Debug information (remove in production) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
            <h3>Debug Information</h3>
            <p>Server: <?= $_SERVER['SERVER_NAME'] ?></p>
            <p>Document Root: <?= $_SERVER['DOCUMENT_ROOT'] ?></p>
            <p>Base Path: <?= $basePath ?></p>
            <p>Sale ID: <?= $sale_id ?></p>
            <p>Error: <?= $error_message ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Receipt wrapper -->
        <div class="receipt-wrapper">
            <?php if (!empty($error_message)): ?>
            <!-- Error message -->
            <div style="text-align:center; color:#e53e3e; padding:20px;">
                <strong style="font-size:16px; display:block; margin-bottom:10px;">Error</strong>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
            <?php elseif ($sale && $sale_items): ?>
            <!-- Receipt content -->
            <div class="receipt">
                <!-- Company header -->
                <div class="receipt-header">
                    <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
                    <?php if (!empty($company_address)): ?>
                    <div class="company-info"><?= htmlspecialchars($company_address) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($company_phone)): ?>
                    <div class="company-info">Tel: <?= htmlspecialchars($company_phone) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($company_email)): ?>
                    <div class="company-info"><?= htmlspecialchars($company_email) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="receipt-title">SALES RECEIPT</div>
                
                <div class="receipt-info">
                    <div>Invoice: <?= htmlspecialchars($sale['invoice_number']) ?></div>
                    <div>Date: <?= date('Y-m-d H:i', strtotime($sale['sale_date'])) ?></div>
                </div>
                
                <div class="divider"></div>
                
                <?php if (!empty($sale['customer_name'])): ?>
                <div class="customer-info">
                    <strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?>
                    <?php if (!empty($sale['customer_phone'])): ?>
                    (<?= htmlspecialchars($sale['customer_phone']) ?>)
                    <?php endif; ?>
                </div>
                <div class="divider"></div>
                <?php endif; ?>
                
                <!-- Items table -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_items as $item): 
                            // Set default values for null/undefined properties
                            $itemName = '';
                            if ($item['item_type'] === 'product' && isset($item['product_name'])) {
                                $itemName = $item['product_name'];
                            } elseif ($item['item_type'] === 'fuel' && isset($item['fuel_name'])) {
                                $itemName = $item['fuel_name'];
                            } else {
                                $itemName = 'Unknown Item';
                            }
                            
                            $quantity = 0;
                            if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                                $quantity = number_format($item['quantity'], $item['item_type'] === 'fuel' ? 2 : 0);
                            }
                            
                            $unit = $item['item_type'] === 'fuel' ? 'L' : '';
                            $unit_price = isset($item['unit_price']) ? number_format($item['unit_price'], 2) : '0.00';
                            $net_amount = isset($item['net_amount']) ? number_format($item['net_amount'], 2) : '0.00';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($itemName) ?></td>
                            <td><?= $quantity ?><?= $unit ?></td>
                            <td><?= $unit_price ?></td>
                            <td><?= $net_amount ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="divider"></div>
                
                <!-- Summary table -->
                <table class="summary-table">
                    <tr>
                        <td>Subtotal:</td>
                        <td><?= $currency_symbol ?> <?= number_format($sale['total_amount'] ?? 0, 2) ?></td>
                    </tr>
                    <?php if (isset($sale['discount_amount']) && $sale['discount_amount'] > 0): ?>
                    <tr>
                        <td>Discount:</td>
                        <td>-<?= $currency_symbol ?> <?= number_format($sale['discount_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Tax (VAT):</td>
                        <td><?= $currency_symbol ?> <?= number_format($sale['tax_amount'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="summary-total">Total:</td>
                        <td class="summary-total"><?= $currency_symbol ?> <?= number_format($sale['net_amount'] ?? 0, 2) ?></td>
                    </tr>
                </table>
                
                <div class="divider"></div>
                
                <!-- Payment information -->
                <div class="payment-info">
                    <table>
                        <tr>
                            <td>Paid By:</td>
                            <td><?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'Unknown')) ?></td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td><?= ucfirst($sale['payment_status'] ?? 'Unknown') ?></td>
                        </tr>
                        <?php 
                        $staffName = '';
                        if (isset($sale['first_name']) || isset($sale['last_name'])) {
                            $staffName = trim(($sale['first_name'] ?? '') . ' ' . ($sale['last_name'] ?? ''));
                        }
                        
                        if (!empty($staffName)): 
                        ?>
                        <tr>
                            <td>Staff:</td>
                            <td><?= htmlspecialchars($staffName) ?></td>
                        </tr>
                        <?php elseif (isset($sale['staff_id']) && !empty($sale['staff_id'])): ?>
                        <tr>
                            <td>Staff ID:</td>
                            <td><?= htmlspecialchars($sale['staff_id']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <?php if (isset($sale['notes']) && !empty($sale['notes'])): ?>
                <div class="divider"></div>
                <div style="font-size:9pt; margin-top:5px;">
                    <strong>Notes:</strong>
                    <p><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="divider"></div>
                
                <!-- Footer -->
                <div class="receipt-footer">
                    <div class="footer-message"><?= htmlspecialchars($receipt_footer) ?></div>
                    <div class="footer-info">Generated: <?= date('Y-m-d H:i') ?></div>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align:center; color:#e53e3e; padding:20px;">
                <strong style="font-size:16px; display:block; margin-bottom:10px;">Error</strong>
                <p>Could not load sale details.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cross-browser printing script -->
    <script>
        // More compatible print function
        function printReceipt() {
            try {
                // Fix for Firefox
                document.body.focus();
                
                // Trigger print dialog
                window.print();
                
                // Event handler after printing
                window.addEventListener('afterprint', function() {
                    // Uncomment if you want to close window after printing
                    // window.close();
                    
                    // Or redirect back to POS
                    // window.location.href = "index.php";
                });
            } catch (e) {
                console.error("Printing error:", e);
                alert("There was an error while trying to print. Please try again or use the browser's print function.");
            }
        }
        
        // Auto-print on load
        window.addEventListener('load', function() {
            // Delay to ensure full rendering across different browsers
            setTimeout(function() {
                try {
                    <?php if (empty($error_message) && $sale): ?>
                    // Only auto-print if no errors and sale data exists
                    printReceipt();
                    <?php endif; ?>
                } catch (e) {
                    console.error("Auto-print error:", e);
                }
            }, 1000); // Increased delay for better compatibility
        });
    </script>
</body>
</html>
