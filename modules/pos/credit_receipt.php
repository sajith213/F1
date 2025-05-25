<?php
/**
 * Credit Sale Receipt Print Template (Thermal Style)
 *
 * This file generates a standalone printable receipt for credit sales,
 * styled like a compact thermal POS receipt.
 */

// --- Configuration & Dependencies ---
// Ensure this path is correct for your file structure.
require_once '../../includes/db.php'; // Contains database connection ($conn)

// --- Initialization & Input Validation ---
$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($saleId <= 0) {
    echo "<!DOCTYPE html><html><head><title>Error</title><style>body{font-family: Arial, sans-serif; text-align: center; padding-top: 50px;}</style></head><body><h1>Invalid Sale ID</h1><p>A valid sale ID is required.</p></body></html>";
    exit;
}

// Initialize variables
$sale = null;
$saleItems = [];
$customerForReceipt = null; // Using a different name to avoid confusion if 'customer' is used elsewhere

// --- Database Operations ---
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in credit_receipt_thermal_print.php: " . ($conn->connect_error ?? 'Connection object not set'));
    echo "<!DOCTYPE html><html><head><title>Error</title><style>body{font-family: Arial, sans-serif; text-align: center; padding-top: 50px;}</style></head><body><h1>Database Error</h1><p>Could not connect to the database.</p></body></html>";
    exit;
}

// Fetch main sale details including staff (from users table) and basic credit customer name
$stmt_sale = $conn->prepare("
    SELECT s.*,
           u.full_name as staff_full_name, /* Renamed to avoid conflict with user table structure if different */
           cc.customer_name as credit_customer_receipt_name /* Specific for this receipt's display */
    FROM sales s
    LEFT JOIN users u ON s.staff_id = u.user_id /* Assuming staff_id in sales maps to user_id in users */
    LEFT JOIN credit_customers cc ON s.credit_customer_id = cc.customer_id
    WHERE s.sale_id = ?
");

if ($stmt_sale) {
    $stmt_sale->bind_param("i", $saleId);
    $stmt_sale->execute();
    $result_sale = $stmt_sale->get_result();
    if ($result_sale && $result_sale->num_rows > 0) {
        $sale = $result_sale->fetch_assoc();
    }
    $stmt_sale->close();
} else {
    error_log("Failed to prepare statement for sale details: " . $conn->error);
}

if (!$sale) {
    echo "<!DOCTYPE html><html><head><title>Error</title><style>body{font-family: Arial, sans-serif; text-align: center; padding-top: 50px;}</style></head><body><h1>Sale Not Found</h1><p>Sale ID " . htmlspecialchars($saleId) . " not found.</p></body></html>";
    exit;
}
if (!isset($sale['credit_customer_id']) || empty($sale['credit_customer_id'])) {
    echo "<!DOCTYPE html><html><head><title>Error</title><style>body{font-family: Arial, sans-serif; text-align: center; padding-top: 50px;}</style></head><body><h1>Not a Credit Sale</h1><p>This receipt is for credit sales only.</p></body></html>";
    exit;
}

// Fetch sale items
$stmt_items = $conn->prepare("
    SELECT si.*,
           CASE
               WHEN si.item_type = 'product' THEN p.product_name
               WHEN si.item_type = 'fuel' THEN ft.fuel_name
               ELSE 'Unknown Item'
           END AS item_display_name
    FROM sale_items si
    LEFT JOIN products p ON si.item_type = 'product' AND si.product_id = p.product_id
    LEFT JOIN pump_nozzles pn ON si.item_type = 'fuel' AND si.nozzle_id = pn.nozzle_id
    LEFT JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
    WHERE si.sale_id = ?
    ORDER BY si.item_id ASC
");
if ($stmt_items) {
    $stmt_items->bind_param("i", $saleId);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        if (!isset($row['net_amount'])) { // Fallback calculation
            $row['net_amount'] = ($row['quantity'] * $row['unit_price']) - ($row['discount_amount'] ?? 0);
        }
        $saleItems[] = $row;
    }
    $stmt_items->close();
} else {
    error_log("Failed to prepare statement for sale items: " . $conn->error);
}

// Fetch detailed credit customer information and balance after this sale
if ($sale['credit_customer_id']) {
    $stmt_cust = $conn->prepare("
        SELECT c.customer_name, c.phone_number, c.email, c.address,
               (SELECT balance_after FROM credit_transactions
                WHERE customer_id = c.customer_id AND sale_id = ?
                ORDER BY transaction_id DESC LIMIT 1) AS balance_after_this_sale
        FROM credit_customers c
        WHERE c.customer_id = ?
    ");
    if ($stmt_cust) {
        $stmt_cust->bind_param("ii", $saleId, $sale['credit_customer_id']);
        $stmt_cust->execute();
        $result_cust = $stmt_cust->get_result();
        if ($result_cust && $result_cust->num_rows > 0) {
            $customerForReceipt = $result_cust->fetch_assoc();
        }
        $stmt_cust->close();
    } else {
        error_log("Failed to prepare statement for credit customer details: " . $conn->error);
    }
}


// Fetch company/system settings (similar to your first receipt script)
$company_name = 'Your Company Name';
$company_address = '';
$company_phone = '';
$company_email = '';
$receipt_footer = 'Thank You for your business!';
$currency_symbol = 'Rs.'; // Default currency

$settings_query = "SELECT setting_name, setting_value FROM system_settings
                   WHERE setting_name IN ('company_name', 'company_address', 'company_phone',
                                          'company_email', 'receipt_footer', 'currency_symbol')";
$result_settings = $conn->query($settings_query);
if ($result_settings) {
    while ($row_setting = $result_settings->fetch_assoc()) {
        // Dynamically assign setting values to variables
        // e.g., $company_name = 'Actual Name from DB'
        ${$row_setting['setting_name']} = $row_setting['setting_value'];
    }
}
// Staff name for the receipt
$staffNameDisplay = $sale['staff_full_name'] ?? 'N/A';
if (empty(trim($staffNameDisplay)) && isset($sale['staff_id'])) {
    $staffNameDisplay = 'Staff ID: ' . htmlspecialchars($sale['staff_id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Receipt #<?= htmlspecialchars($sale['invoice_number'] ?? $saleId) ?></title>
    <style>
        /* Styles adapted from your original POS receipt for thermal printer look */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', 'Helvetica Neue', 'Helvetica', sans-serif; /* Common sans-serif fonts */
            font-size: 10pt; /* Base size for thermal receipts, adjust if needed */
            line-height: 1.4;
            color: #000;
            background: #fff; /* Important for printing */
        }
        .receipt-wrapper {
            width: 300px; /* Approx 80mm, adjust based on printer specs and testing */
            max-width: 100%;
            margin: 10px auto; /* Centering for screen view, print margins handle paper */
            padding: 10px; /* Padding inside the wrapper */
            background: white;
            /* box-shadow: 0 0 5px rgba(0,0,0,0.15); /* Optional shadow for screen */
        }
        .receipt-header { text-align: center; margin-bottom: 10px; }
        .company-name { font-size: 13pt; font-weight: bold; margin-bottom: 3px; }
        .company-info { font-size: 8pt; color: #333; margin-bottom: 2px; }
        .receipt-title { margin: 8px 0; text-align: center; font-weight: bold; font-size: 11pt; text-transform: uppercase;}
        .receipt-info, .customer-details-receipt { font-size: 9pt; margin-bottom: 8px; }
        .receipt-info div, .customer-details-receipt div { margin-bottom: 2px; }
        .divider { border-top: 1px dashed #555; margin: 8px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9pt; }
        .items-table th {
            text-align: left; padding: 4px 2px; border-bottom: 1px solid #333; font-size: 9pt;
        }
        .items-table td { padding: 3px 2px; border-bottom: 1px dotted #ccc; vertical-align: top;}
        .items-table th:nth-child(2), .items-table td:nth-child(2) { text-align: center; } /* Qty */
        .items-table th:nth-child(3), .items-table td:nth-child(3) { text-align: right; } /* Price */
        .items-table th:last-child, .items-table td:last-child { text-align: right; } /* Total */

        .summary-table { width: 100%; margin: 8px 0; font-size: 9pt; }
        .summary-table td { padding: 2px 0; }
        .summary-table td:last-child { text-align: right; }
        .summary-total { font-weight: bold; font-size: 10pt; padding-top: 3px !important; border-top: 1px solid #000; }

        .payment-info-receipt { margin-top: 8px; font-size: 9pt; }
        .payment-info-receipt table { width: 100%; }
        .payment-info-receipt td { padding: 1px 0; }
        .payment-info-receipt td:last-child { text-align: right; }

        .receipt-footer { margin-top: 10px; text-align: center; font-size: 8pt; }
        .footer-message { font-weight: bold; margin-bottom: 3px; }
        .footer-info { font-size: 7pt; color: #555; }

        /* Print-specific styles */
        @media print {
            body {
                font-size: 9pt; /* Can slightly reduce for print if needed */
                width: auto; /* Let printer decide width based on paper */
                margin: 0;
                padding: 0;
            }
            .receipt-wrapper {
                width: 100%; /* For thermal, it's usually the paper width e.g. 72mm, 76mm, 80mm */
                max-width: 80mm; /* Standard thermal receipt width constraint */
                margin: 0;
                padding: 2mm 1mm; /* Minimal padding for print */
                border: none;
                box-shadow: none;
            }
            .no-print { display: none !important; }
            .divider { border-top-style: dashed; border-color: #333; }
            .items-table th { font-size: 8.5pt;}
            .items-table td { font-size: 8.5pt; padding: 2px 1px;}
            .company-name { font-size: 12pt; }
            .receipt-title { font-size: 10pt;}
            .summary-total { font-size: 9.5pt; }

            /* Ensure content fits and doesn't break too awkwardly */
            .items-table, .summary-table, .payment-info-receipt {
                page-break-inside: avoid;
            }
            @page {
                margin: 2mm; /* Adjust margins for the thermal printer */
                /* size: 80mm auto; /* Example for specific paper size, may not be needed for all thermal printers */
            }
        }
    </style>
</head>
<body>
    <div class="receipt-wrapper">
        <div class="receipt">
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

            <div class="receipt-title">Credit Sale Receipt</div>

            <div class="receipt-info">
                <div>Invoice: <?= htmlspecialchars($sale['invoice_number'] ?? 'N/A') ?></div>
                <div>Date: <?= isset($sale['sale_date']) ? date('Y-m-d H:i', strtotime($sale['sale_date'])) : 'N/A' ?></div>
            </div>

            <?php if ($customerForReceipt && !empty($customerForReceipt['customer_name'])): ?>
            <div class="customer-details-receipt">
                <div>Customer: <?= htmlspecialchars($customerForReceipt['customer_name']) ?></div>
                <?php if (isset($sale['due_date'])): ?>
                    <div>Due Date: <?= date('Y-m-d', strtotime($sale['due_date'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="divider"></div>

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
                    <?php foreach ($saleItems as $item):
                        $itemName = $item['item_display_name'] ?? 'Unknown Item';
                        $quantity = $item['quantity'] ?? 0;
                        $unit = ($item['item_type'] ?? '') === 'fuel' ? 'L' : '';
                        $quantityFormatted = number_format($quantity, ($item['item_type'] === 'fuel' ? 2 : 0));
                        $unitPrice = $item['unit_price'] ?? 0;
                        $netAmount = $item['net_amount'] ?? 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($itemName) ?></td>
                        <td><?= $quantityFormatted ?><?= $unit ?></td>
                        <td><?= number_format($unitPrice, 2) ?></td>
                        <td><?= number_format($netAmount, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="divider"></div>

            <table class="summary-table">
                <tr>
                    <td>Subtotal:</td>
                    <td><?= htmlspecialchars($currency_symbol) ?> <?= number_format($sale['total_amount'] ?? 0, 2) ?></td>
                </tr>
                <?php if (isset($sale['discount_amount']) && $sale['discount_amount'] > 0): ?>
                <tr>
                    <td>Discount:</td>
                    <td>-<?= htmlspecialchars($currency_symbol) ?> <?= number_format($sale['discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                 <?php if (isset($sale['tax_amount']) && $sale['tax_amount'] != 0): // Show tax only if applicable ?>
                <tr>
                    <td>Tax:</td>
                    <td><?= htmlspecialchars($currency_symbol) ?> <?= number_format($sale['tax_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="summary-total">Total Due:</td>
                    <td class="summary-total"><?= htmlspecialchars($currency_symbol) ?> <?= number_format($sale['net_amount'] ?? 0, 2) ?></td>
                </tr>
                <?php if ($customerForReceipt && isset($customerForReceipt['balance_after_this_sale'])): ?>
                <tr>
                    <td>Balance After Sale:</td>
                    <td><?= htmlspecialchars($currency_symbol) ?> <?= number_format($customerForReceipt['balance_after_this_sale'], 2) ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <div class="divider"></div>

            <div class="payment-info-receipt">
                <table>
                    <tr>
                        <td>Paid By:</td>
                        <td><?= ucfirst(htmlspecialchars(str_replace('_', ' ', $sale['payment_method'] ?? 'Credit'))) ?></td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td><?= ucfirst(htmlspecialchars($sale['payment_status'] ?? 'Unpaid')) ?></td>
                    </tr>
                    <tr>
                        <td>Staff:</td>
                        <td><?= htmlspecialchars($staffNameDisplay) ?></td>
                    </tr>
                </table>
            </div>

            <?php if (isset($sale['notes']) && !empty(trim($sale['notes']))): ?>
                <div class="divider"></div>
                <div style="font-size:8pt; margin-top:5px;">
                    <strong>Notes:</strong>
                    <p><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="divider"></div>

            <div class="receipt-footer">
                <div class="footer-message"><?= htmlspecialchars($receipt_footer) ?></div>
                <div class="footer-info">Generated: <?= date('Y-m-d H:i') ?></div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function() {
            try {
                // Ensure content is rendered before printing, especially for images if any
                setTimeout(function() {
                    window.print();
                }, 500); // Small delay, adjust if needed
            } catch (e) {
                console.error("Printing error:", e);
                alert("Could not initiate print. Please use browser's print function.");
            }
        });
    </script>
</body>
</html>