<?php
/**
 * Credit Management - Add Credit Settlement
 * 
 * This file provides a form to record a new credit settlement payment
 */

// Set page title and include header
$page_title = "Add Credit Settlement";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="credit_settlements.php">Credit Settlements</a> / <span class="text-gray-700">Add Settlement</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$errors = [];
$success = false;
$settlement_id = 0;

// Get all credit customers with outstanding balances
$customers = [];
$stmt = $conn->prepare("
    SELECT c.customer_id, c.customer_name, c.phone_number, c.current_balance, c.credit_limit 
    FROM credit_customers c
    WHERE c.status = 'active' AND c.current_balance > 0
    ORDER BY c.customer_name
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    $stmt->close();
}

// Get customer details if customer_id is provided
$customer = null;
if ($customer_id > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM credit_customers WHERE customer_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $customer = $result->fetch_assoc();
        }
        
        $stmt->close();
    }
}

// Get outstanding invoices for the selected customer
$outstanding_invoices = [];
if ($customer_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            s.sale_id,
            s.invoice_number,
            s.sale_date,
            s.net_amount,
            s.due_date,
            DATEDIFF(CURRENT_DATE, s.due_date) as days_overdue,
            (SELECT COALESCE(SUM(ct.amount), 0)
             FROM credit_transactions ct
             WHERE ct.sale_id = s.sale_id AND ct.transaction_type = 'payment') as amount_paid,
            (s.net_amount - (SELECT COALESCE(SUM(ct.amount), 0)
                            FROM credit_transactions ct
                            WHERE ct.sale_id = s.sale_id AND ct.transaction_type = 'payment')) as balance_due
        FROM sales s
        WHERE s.credit_customer_id = ? AND s.credit_status != 'settled'
        ORDER BY days_overdue DESC, s.due_date ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $outstanding_invoices[] = $row;
        }
        
        $stmt->close();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_settlement'])) {
    // Get form data
    $selected_customer_id = trim($_POST['customer_id'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $settlement_date = trim($_POST['settlement_date'] ?? date('Y-m-d'));
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $apply_to_invoices = isset($_POST['apply_to_invoices']) && $_POST['apply_to_invoices'] === 'on';
    $selected_invoices = $_POST['invoices'] ?? [];
    
    // Validate form data
    if (empty($selected_customer_id)) {
        $errors[] = "Please select a customer";
    }
    
    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Please select a payment method";
    }
    
    if (empty($settlement_date)) {
        $errors[] = "Please enter a settlement date";
    }
    
    // If applying to specific invoices, make sure they're selected
    if ($apply_to_invoices && empty($selected_invoices)) {
        $errors[] = "Please select at least one invoice";
    }
    
    // Validate that payment amount matches selected invoices if applying to specific invoices
    if ($apply_to_invoices && !empty($selected_invoices)) {
        // Calculate total of selected invoices
        $invoice_total = 0;
        foreach ($selected_invoices as $inv) {
            $invoice_parts = explode('|', $inv);
            if (count($invoice_parts) === 2) {
                $invoice_total += (float)$invoice_parts[1];
            }
        }
        
        // Round to 2 decimal places for comparison
        $invoice_total = round($invoice_total, 2);
        $amount = round($amount, 2);
        
        if ($invoice_total != $amount) {
            $errors[] = "The payment amount ({$amount}) does not match the total of selected invoices ({$invoice_total})";
        }
    }
    
    // If no errors, process the settlement
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Insert settlement record
            $stmt = $conn->prepare("
                INSERT INTO credit_settlements (
                    customer_id, settlement_date, amount, payment_method, 
                    reference_no, notes, recorded_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param(
                "isdsssi", 
                $selected_customer_id, $settlement_date, $amount, $payment_method,
                $reference_no, $notes, $user_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating settlement: " . $stmt->error);
            }
            
            $settlement_id = $conn->insert_id;
            $stmt->close();
            
            // Update customer balance
            $stmt = $conn->prepare("
                UPDATE credit_customers 
                SET current_balance = current_balance - ? 
                WHERE customer_id = ?
            ");
            
            $stmt->bind_param("di", $amount, $selected_customer_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating customer balance: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Get the customer's current balance after update
            $stmt = $conn->prepare("SELECT current_balance FROM credit_customers WHERE customer_id = ?");
            $stmt->bind_param("i", $selected_customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $updated_balance = $result->fetch_assoc()['current_balance'];
            $stmt->close();
            
            // Create credit transaction record
            $stmt = $conn->prepare("
                INSERT INTO credit_transactions (
                    customer_id, transaction_date, amount, transaction_type,
                    balance_after, reference_no, notes, created_by
                ) VALUES (?, ?, ?, 'payment', ?, ?, ?, ?)
            ");
            
            $transaction_ref = "PMNT-" . $settlement_id;
            $stmt->bind_param(
                "isddssi",
                $selected_customer_id, $settlement_date, $amount, 
                $updated_balance, $transaction_ref, $notes, $user_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating transaction record: " . $stmt->error);
            }
            
            $transaction_id = $conn->insert_id;
            $stmt->close();
            
            // If applying to specific invoices, update them
            if ($apply_to_invoices && !empty($selected_invoices)) {
                foreach ($selected_invoices as $inv) {
                    $invoice_parts = explode('|', $inv);
                    if (count($invoice_parts) === 2) {
                        $invoice_id = (int)$invoice_parts[0];
                        $payment_amount = (float)$invoice_parts[1];
                        
                        // Create transaction record for this invoice payment
                        $stmt = $conn->prepare("
                            INSERT INTO credit_transactions (
                                customer_id, sale_id, transaction_date, amount, transaction_type,
                                balance_after, reference_no, notes, created_by
                            ) VALUES (?, ?, ?, ?, 'payment', ?, ?, ?, ?)
                        ");
                        
                        $invoice_ref = "INV-" . $invoice_id . "-PMNT-" . $settlement_id;
                        $invoice_note = "Payment applied to Invoice #" . $invoice_id;
                        
                        $stmt->bind_param(
                            "iisddssi",
                            $selected_customer_id, $invoice_id, $settlement_date, $payment_amount,
                            $updated_balance, $invoice_ref, $invoice_note, $user_id
                        );
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error creating invoice payment record: " . $stmt->error);
                        }
                        
                        $stmt->close();
                        
                        // Check if invoice is fully paid and update its status
                        $stmt = $conn->prepare("
                            SELECT 
                                s.net_amount,
                                (SELECT COALESCE(SUM(ct.amount), 0)
                                 FROM credit_transactions ct
                                 WHERE ct.sale_id = s.sale_id AND ct.transaction_type = 'payment') as total_paid
                            FROM sales s
                            WHERE s.sale_id = ?
                        ");
                        
                        $stmt->bind_param("i", $invoice_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $invoice_data = $result->fetch_assoc();
                        $stmt->close();
                        
                        $invoice_amount = $invoice_data['net_amount'];
                        $total_paid = $invoice_data['total_paid'];
                        
                        // Round to handle floating point comparison issues
                        $invoice_amount = round($invoice_amount, 2);
                        $total_paid = round($total_paid, 2);
                        
                        $new_status = 'partial';
                        if ($total_paid >= $invoice_amount) {
                            $new_status = 'settled';
                        }
                        
                        // Update invoice status
                        $stmt = $conn->prepare("
                            UPDATE sales 
                            SET credit_status = ?
                            WHERE sale_id = ?
                        ");
                        
                        $stmt->bind_param("si", $new_status, $invoice_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error updating invoice status: " . $stmt->error);
                        }
                        
                        $stmt->close();
                    }
                }
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Set success flag and message
            $success = true;
            $_SESSION['success_message'] = "Settlement recorded successfully.";
            
            // Redirect to receipt page
            header("Location: settlement_receipt.php?id=" . $settlement_id);
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $errors[] = "Error processing settlement: " . $e->getMessage();
        }
    }
}

// Get currency symbol from settings
$currency_symbol = '$';
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currency_symbol = $row['setting_value'];
    }
    $stmt->close();
}
?>

<div class="container mx-auto pb-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-800">Add Credit Settlement</h3>
                <a href="credit_settlements.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Settlements
                </a>
            </div>
        </div>
        
        <form method="POST" action="" id="settlement-form" class="p-6">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Please fix the following errors:</strong>
                <ul class="mt-1 ml-4 list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Customer Selection -->
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Customer <span class="text-red-600">*</span>
                    </label>
                    <select name="customer_id" id="customer_id" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>" 
                                data-balance="<?= $c['current_balance'] ?>"
                                <?= ($customer_id == $c['customer_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['customer_name']) ?> 
                            (<?= $currency_symbol ?> <?= number_format($c['current_balance'], 2) ?> balance)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">
                        Amount <span class="text-red-600">*</span>
                    </label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                        </div>
                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" required
                               class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md"
                               placeholder="0.00" value="<?= isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : '' ?>">
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">
                        Payment Method <span class="text-red-600">*</span>
                    </label>
                    <select name="payment_method" id="payment_method" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="">Select Payment Method</option>
                        <option value="cash" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="bank_transfer" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="check" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'check') ? 'selected' : '' ?>>Check</option>
                        <option value="mobile_payment" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'mobile_payment') ? 'selected' : '' ?>>Mobile Payment</option>
                    </select>
                </div>
                
                <!-- Settlement Date -->
                <div>
                    <label for="settlement_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Settlement Date <span class="text-red-600">*</span>
                    </label>
                    <input type="date" name="settlement_date" id="settlement_date" required
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           value="<?= isset($_POST['settlement_date']) ? htmlspecialchars($_POST['settlement_date']) : date('Y-m-d') ?>">
                </div>
                
                <!-- Reference Number -->
                <div>
                    <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">
                        Reference Number
                    </label>
                    <input type="text" name="reference_no" id="reference_no"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="Check number, transfer reference, etc."
                           value="<?= isset($_POST['reference_no']) ? htmlspecialchars($_POST['reference_no']) : '' ?>">
                </div>
                
                <!-- Notes -->
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="Additional details about this payment"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                </div>
            </div>
            
            <!-- Customer Information and Outstanding Invoices -->
            <div id="customer-details" class="mt-8 <?= $customer ? '' : 'hidden' ?>">
                <h4 class="text-lg font-medium text-gray-800 mb-3">Customer Information</h4>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Customer Name</p>
                            <p class="font-medium" id="detail-customer-name"><?= $customer ? htmlspecialchars($customer['customer_name']) : '' ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Phone Number</p>
                            <p class="font-medium" id="detail-phone"><?= $customer ? htmlspecialchars($customer['phone_number']) : '' ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Current Balance</p>
                            <p class="font-medium text-red-600" id="detail-balance">
                                <?= $customer ? $currency_symbol . ' ' . number_format($customer['current_balance'], 2) : '' ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Apply to specific invoices toggle -->
                <div class="flex items-center mb-4">
                    <input id="apply_to_invoices" name="apply_to_invoices" type="checkbox" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                           <?= isset($_POST['apply_to_invoices']) && $_POST['apply_to_invoices'] === 'on' ? 'checked' : '' ?>>
                    <label for="apply_to_invoices" class="ml-2 block text-sm text-gray-900">
                        Apply payment to specific invoices
                    </label>
                </div>
                
                <!-- Outstanding Invoices Table -->
                <div id="invoices-section" class="<?= isset($_POST['apply_to_invoices']) && $_POST['apply_to_invoices'] === 'on' ? '' : 'hidden' ?>">
                    <h4 class="text-lg font-medium text-gray-800 mb-3">Outstanding Invoices</h4>
                    
                    <?php if (empty($outstanding_invoices)): ?>
                    <div class="bg-gray-50 p-4 rounded-lg text-center text-gray-500">
                        <p>No outstanding invoices found for this customer.</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <div class="flex items-center">
                                            <input id="select-all-invoices" type="checkbox" 
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="select-all-invoices" class="ml-2">Select</label>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($outstanding_invoices as $invoice): ?>
                                <tr class="invoice-row hover:bg-gray-50" data-balance="<?= number_format($invoice['balance_due'], 2, '.', '') ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="invoices[]" value="<?= $invoice['sale_id'] ?>|<?= $invoice['balance_due'] ?>"
                                               class="invoice-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                               data-amount="<?= $invoice['balance_due'] ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                        <a href="../pos/receipt.php?id=<?= $invoice['sale_id'] ?>" target="_blank">
                                            <?= htmlspecialchars($invoice['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($invoice['sale_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <?php
                                        $statusBadge = 'bg-yellow-100 text-yellow-800';
                                        $statusText = 'Pending';
                                        
                                        if ($invoice['days_overdue'] > 0) {
                                            $statusBadge = 'bg-red-100 text-red-800';
                                            $statusText = 'Overdue';
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusBadge ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $currency_symbol ?> <?= number_format($invoice['net_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $currency_symbol ?> <?= number_format($invoice['amount_paid'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium text-right">
                                        <?= $currency_symbol ?> <?= number_format($invoice['balance_due'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-right text-sm font-medium text-gray-900">Total Selected:</td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-gray-900" id="selected-total"><?= $currency_symbol ?> 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-8 flex justify-end space-x-3">
                <a href="credit_settlements.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" name="add_settlement" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Record Settlement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer_id');
    const customerDetails = document.getElementById('customer-details');
    const customerName = document.getElementById('detail-customer-name');
    const customerPhone = document.getElementById('detail-phone');
    const customerBalance = document.getElementById('detail-balance');
    const amountInput = document.getElementById('amount');
    const applyToInvoices = document.getElementById('apply_to_invoices');
    const invoicesSection = document.getElementById('invoices-section');
    const selectAllCheckbox = document.getElementById('select-all-invoices');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const selectedTotal = document.getElementById('selected-total');
    const settlementForm = document.getElementById('settlement-form');
    
    // Update customer details when customer selection changes
    customerSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const customerId = this.value;
        
        if (customerId) {
            // Redirect to the same page with customer_id parameter
            window.location.href = 'add_settlement.php?customer_id=' + customerId;
        } else {
            customerDetails.classList.add('hidden');
        }
    });
    
    // Toggle invoices section when apply to invoices checkbox changes
    applyToInvoices.addEventListener('change', function() {
        if (this.checked) {
            invoicesSection.classList.remove('hidden');
        } else {
            invoicesSection.classList.add('hidden');
        }
    });
    
    // Handle select all checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectedTotal();
        });
    }
    
    // Handle individual invoice checkboxes
    invoiceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedTotal();
            // Check if all checkboxes are checked, and update select all checkbox
            const allChecked = Array.from(invoiceCheckboxes).every(cb => cb.checked);
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
    
    // Update the total amount of selected invoices
    function updateSelectedTotal() {
        let total = 0;
        invoiceCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                total += parseFloat(checkbox.getAttribute('data-amount'));
            }
        });
        
        selectedTotal.textContent = '<?= $currency_symbol ?> ' + total.toFixed(2);
        
        // Update amount input if applying to invoices is checked
        if (applyToInvoices.checked) {
            amountInput.value = total.toFixed(2);
        }
    }
    
    // Form validation
    settlementForm.addEventListener('submit', function(e) {
        if (applyToInvoices.checked) {
            const selectedInvoices = document.querySelectorAll('.invoice-checkbox:checked');
            if (selectedInvoices.length === 0) {
                e.preventDefault();
                alert('Please select at least one invoice to apply the payment to.');
                return false;
            }
            
            // Ensure amount matches selected invoices
            let total = 0;
            selectedInvoices.forEach(checkbox => {
                total += parseFloat(checkbox.getAttribute('data-amount'));
            });
            
            const amount = parseFloat(amountInput.value);
            
            // Round to 2 decimal places for comparison
            const roundedTotal = Math.round(total * 100) / 100;
            const roundedAmount = Math.round(amount * 100) / 100;
            
            if (roundedTotal !== roundedAmount) {
                e.preventDefault();
                alert(`The payment amount (${roundedAmount}) does not match the total of selected invoices (${roundedTotal}). Please adjust the amount or invoice selection.`);
                return false;
            }
        }
    });
    
    // Initialize with any pre-selected invoices
    updateSelectedTotal();
    
    // If specific invoice_id is provided, check that invoice automatically
    <?php if ($invoice_id > 0): ?>
    document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
        const [id, amount] = checkbox.value.split('|');
        if (parseInt(id) === <?= $invoice_id ?>) {
            checkbox.checked = true;
            applyToInvoices.checked = true;
            invoicesSection.classList.remove('hidden');
            updateSelectedTotal();
        }
    });
    <?php endif; ?>
});
</script>

<?php include_once '../../includes/footer.php'; ?>