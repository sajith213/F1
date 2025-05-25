<?php
/**
 * Credit Management - Settlement Receipt
 * 
 * This file generates a receipt for credit settlements
 */

// Set page title
$page_title = "Settlement Receipt";
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Include header (unless in print mode)
if (!$print_mode) {
    include_once '../../includes/header.php';
}

require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Get settlement ID from URL
$settlement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($settlement_id <= 0) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>Invalid settlement ID</div>";
    if (!$print_mode) include_once '../../includes/footer.php';
    exit;
}

// Get settlement details
$settlement = null;
$customer = null;
$invoice_payments = [];

$stmt = $conn->prepare("
    SELECT s.*, c.customer_name, c.phone_number, c.address, c.email, c.current_balance,
           u.full_name as recorded_by_name
    FROM credit_settlements s
    JOIN credit_customers c ON s.customer_id = c.customer_id
    LEFT JOIN users u ON s.recorded_by = u.user_id
    WHERE s.settlement_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $settlement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $settlement = $result->fetch_assoc();
        $customer = [
            'customer_id' => $settlement['customer_id'],
            'customer_name' => $settlement['customer_name'],
            'phone_number' => $settlement['phone_number'],
            'address' => $settlement['address'],
            'email' => $settlement['email'],
            'current_balance' => $settlement['current_balance']
        ];
    } else {
        echo "<div class='bg-red-100 text-red-700 p-4 rounded'>Settlement not found</div>";
        if (!$print_mode) include_once '../../includes/footer.php';
        exit;
    }
    
    $stmt->close();
}

// Get related credit transactions to see if this was applied to specific invoices
$stmt = $conn->prepare("
    SELECT ct.*, s.invoice_number
    FROM credit_transactions ct
    LEFT JOIN sales s ON ct.sale_id = s.sale_id
    WHERE ct.transaction_type = 'payment' 
    AND ct.reference_no LIKE CONCAT('%PMNT-', ?, '%')
    AND ct.sale_id IS NOT NULL
");

if ($stmt) {
    $stmt->bind_param("i", $settlement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $invoice_payments[] = $row;
    }
    
    $stmt->close();
}

// Get company info from settings
$companyName = "Your Company";
$companyAddress = "Company Address";
$companyPhone = "Phone Number";
$companyEmail = "email@example.com";
$currencySymbol = "$";

$stmt = $conn->prepare("
    SELECT setting_name, setting_value 
    FROM system_settings 
    WHERE setting_name IN ('company_name', 'company_address', 'company_phone', 'company_email', 'currency_symbol')
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        switch ($row['setting_name']) {
            case 'company_name':
                $companyName = $row['setting_value'];
                break;
            case 'company_address':
                $companyAddress = $row['setting_value'];
                break;
            case 'company_phone':
                $companyPhone = $row['setting_value'];
                break;
            case 'company_email':
                $companyEmail = $row['setting_value'];
                break;
            case 'currency_symbol':
                $currencySymbol = $row['setting_value'];
                break;
        }
    }
    
    $stmt->close();
}

// Generate unique receipt number if one doesn't exist
$receiptNumber = 'RCPT-' . str_pad($settlement_id, 6, '0', STR_PAD_LEFT);

// Format payment method for display
function formatPaymentMethod($method) {
    return ucwords(str_replace('_', ' ', $method));
}

// Print-specific styles
if ($print_mode):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlement Receipt - <?= $receiptNumber ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.4;
        }
        .receipt {
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
        }
        .header {
            text-align: center;
            margin-bottom: 5mm;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 3mm 0;
        }
        .info-section {
            margin-bottom: 3mm;
        }
        .info-section div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
        }
        .info-title {
            font-weight: bold;
            margin-bottom: 2mm;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
        }
        .items th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding-bottom: 1mm;
        }
        .items .amount {
            text-align: right;
        }
        .total-amount {
            font-weight: bold;
            font-size: 13px;
        }
        .footer {
            text-align: center;
            margin-top: 5mm;
            font-size: 10px;
        }
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
<?php endif; ?>

<div class="<?= $print_mode ? 'receipt' : 'bg-white p-8 mx-auto max-w-2xl rounded-lg shadow-md' ?>">
    <?php if (!$print_mode): ?>
    <div class="mb-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Settlement Receipt</h1>
        <div>
            <a href="?id=<?= $settlement_id ?>&print=1" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                <i class="fas fa-print mr-1"></i> Print
            </a>
            <a href="credit_settlements.php" class="ml-2 bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $print_mode ? 'header' : 'text-center mb-6' ?>">
        <div class="<?= $print_mode ? 'company-name' : 'text-xl font-bold mb-1' ?>"><?= htmlspecialchars($companyName) ?></div>
        <div class="<?= $print_mode ? '' : 'text-gray-600 text-sm' ?>"><?= htmlspecialchars($companyAddress) ?></div>
        <div class="<?= $print_mode ? '' : 'text-gray-600 text-sm' ?>"><?= htmlspecialchars($companyPhone) ?></div>
        <div class="<?= $print_mode ? '' : 'text-gray-600 text-sm' ?>"><?= htmlspecialchars($companyEmail) ?></div>
    </div>

    <div class="<?= $print_mode ? 'divider' : 'border-t border-b border-gray-200 py-4 mb-6' ?>"></div>

    <div class="<?= $print_mode ? 'info-section' : 'mb-6 grid grid-cols-2 gap-4' ?>">
        <div class="<?= $print_mode ? '' : 'text-sm' ?>">
            <span class="<?= $print_mode ? '' : 'text-gray-600' ?>">Receipt No:</span>
            <span class="<?= $print_mode ? '' : 'font-medium' ?>"><?= htmlspecialchars($receiptNumber) ?></span>
        </div>
        <div class="<?= $print_mode ? '' : 'text-sm' ?>">
            <span class="<?= $print_mode ? '' : 'text-gray-600' ?>">Date:</span>
            <span class="<?= $print_mode ? '' : 'font-medium' ?>"><?= date('M d, Y', strtotime($settlement['settlement_date'])) ?></span>
        </div>
        <div class="<?= $print_mode ? '' : 'text-sm' ?>">
            <span class="<?= $print_mode ? '' : 'text-gray-600' ?>">Reference:</span>
            <span class="<?= $print_mode ? '' : 'font-medium' ?>"><?= htmlspecialchars($settlement['reference_no'] ?? 'N/A') ?></span>
        </div>
        <div class="<?= $print_mode ? '' : 'text-sm' ?>">
            <span class="<?= $print_mode ? '' : 'text-gray-600' ?>">Payment Method:</span>
            <span class="<?= $print_mode ? '' : 'font-medium' ?>"><?= formatPaymentMethod($settlement['payment_method']) ?></span>
        </div>
    </div>

    <div class="<?= $print_mode ? 'info-section' : 'mb-6' ?>">
        <div class="<?= $print_mode ? 'info-title' : 'text-lg font-semibold text-gray-800 mb-2' ?>">Customer Information</div>
        <div class="<?= $print_mode ? '' : 'bg-gray-50 p-4 rounded-lg' ?>">
            <div class="<?= $print_mode ? '' : 'text-sm font-medium' ?>"><?= htmlspecialchars($customer['customer_name']) ?></div>
            <div class="<?= $print_mode ? '' : 'text-sm text-gray-600' ?>"><?= htmlspecialchars($customer['phone_number']) ?></div>
            <?php if (!empty($customer['address'])): ?>
            <div class="<?= $print_mode ? '' : 'text-sm text-gray-600' ?>"><?= nl2br(htmlspecialchars($customer['address'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
            <div class="<?= $print_mode ? '' : 'text-sm text-gray-600' ?>"><?= htmlspecialchars($customer['email']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="<?= $print_mode ? 'info-section' : 'mb-6' ?>">
        <div class="<?= $print_mode ? 'info-title' : 'text-lg font-semibold text-gray-800 mb-2' ?>">Payment Details</div>
        <div class="<?= $print_mode ? '' : 'bg-gray-50 p-4 rounded-lg' ?>">
            <div class="<?= $print_mode ? '' : 'flex justify-between mb-2' ?>">
                <span class="<?= $print_mode ? '' : 'text-sm text-gray-600' ?>">Amount Paid:</span>
                <span class="<?= $print_mode ? 'total-amount' : 'text-lg font-semibold' ?>"><?= $currencySymbol ?> <?= number_format($settlement['amount'], 2) ?></span>
            </div>
            <div class="<?= $print_mode ? '' : 'flex justify-between' ?>">
                <span class="<?= $print_mode ? '' : 'text-sm text-gray-600' ?>">Balance After Payment:</span>
                <span class="<?= $print_mode ? '' : 'text-sm font-medium' ?>"><?= $currencySymbol ?> <?= number_format($customer['current_balance'], 2) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($invoice_payments)): ?>
    <div class="<?= $print_mode ? 'info-section' : 'mb-6' ?>">
        <div class="<?= $print_mode ? 'info-title' : 'text-lg font-semibold text-gray-800 mb-2' ?>">Applied to Invoices</div>
        
        <table class="<?= $print_mode ? 'items' : 'min-w-full divide-y divide-gray-200' ?>">
            <thead class="<?= $print_mode ? '' : 'bg-gray-50' ?>">
                <tr>
                    <th class="<?= $print_mode ? '' : 'px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider' ?>">Invoice</th>
                    <th class="<?= $print_mode ? 'amount' : 'px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider' ?>">Amount</th>
                </tr>
            </thead>
            <tbody class="<?= $print_mode ? '' : 'bg-white divide-y divide-gray-200' ?>">
                <?php foreach ($invoice_payments as $payment): ?>
                <tr>
                    <td class="<?= $print_mode ? '' : 'px-4 py-2 whitespace-nowrap text-sm text-gray-900' ?>"><?= htmlspecialchars($payment['invoice_number']) ?></td>
                    <td class="<?= $print_mode ? 'amount' : 'px-4 py-2 whitespace-nowrap text-sm text-gray-900 text-right' ?>"><?= $currencySymbol ?> <?= number_format($payment['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($settlement['notes'])): ?>
    <div class="<?= $print_mode ? 'info-section' : 'mb-6' ?>">
        <div class="<?= $print_mode ? 'info-title' : 'text-lg font-semibold text-gray-800 mb-2' ?>">Notes</div>
        <div class="<?= $print_mode ? '' : 'bg-gray-50 p-4 rounded-lg text-sm text-gray-700' ?>">
            <?= nl2br(htmlspecialchars($settlement['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $print_mode ? 'footer' : 'text-center text-sm text-gray-500 mt-8' ?>">
        <p>Thank you for your payment!</p>
        <p>This receipt was generated on <?= date('M d, Y h:i A') ?></p>
        <p>Recorded by: <?= htmlspecialchars($settlement['recorded_by_name'] ?? 'System') ?></p>
    </div>

    <?php if (!$print_mode): ?>
    <div class="mt-8 flex justify-end">
        <a href="credit_settlements.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">
            <i class="fas fa-arrow-left mr-1"></i> Back to Settlements
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
if ($print_mode) {
    echo "</body></html>";
} else {
    include_once '../../includes/footer.php';
}
?>